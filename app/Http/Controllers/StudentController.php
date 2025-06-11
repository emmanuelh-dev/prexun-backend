<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Promocion;
use App\Models\SemanaIntensiva;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\Transaction;
use App\Services\Moodle\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use PDO;

class StudentController extends Controller
{
    protected $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * Display a listing of students.
     */
    public function index(Request $request)
    {
        $campus_id = $request->get('campus_id');
        $search = $request->get('search');
        $searchDate = $request->get('searchDate');
        $searchPhone = $request->get('searchPhone');
        $searchMatricula = $request->get('searchMatricula');
        $period = $request->get('period');
        $grupo = $request->get('grupo');
        $semanaIntensivaId = $request->get('semanaIntensivaFilter');
        $perPage = $request->get('perPage', 10);
        $page = $request->get('page', 1);

        Log::info('Fetching students', [
            'campus_id' => $campus_id,
            'search' => $search,
            'searchDate' => $searchDate,
            'searchPhone' => $searchPhone,
            'searchMatricula' => $searchMatricula,
            'period' => $period,
            'perPage' => $perPage,
            'page' => $page,
            'grupo' => $grupo
        ]);

        $query = Student::with(['period', 'transactions', 'municipio', 'prepa', 'facultad', 'carrera', 'grupo']);

        if ($campus_id) {
            $query->where('campus_id', $campus_id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($searchPhone) {
            $query->where('phone', 'LIKE', "%{$searchPhone}%");
        }

        if ($searchMatricula) {
            $query->where('id', 'LIKE', "%{$searchMatricula}%");
        }

        if ($searchDate) {
            $query->whereDate('created_at', $searchDate);
        }

        if ($period) {
            $query->where('period_id', $period);
        }

        if ($grupo) {
            $query->where('grupo_id', $grupo);
        }

        if ($semanaIntensivaId) {
            $query->where('semana_intensiva_id', $semanaIntensivaId);
        }

        $students = $query->paginate($perPage, ['*'], 'page', $page);

        foreach ($students as $student) {
            $periodCost = $student->period ? $student->period->price : 0;
            $totalPaid = $student->transactions->sum('amount');
            $student->current_debt = $periodCost - $totalPaid;
        }

        return response()->json($students);
    }

    /**
     * Store a new student.
     */
    public function store(Request $request)
    {
        $validator = $this->validateStudent($request);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $student = Student::create($request->all());
            $username = (string) $student->id;

            $moodleUser = $this->prepareMoodleUser($student);
            $moodleResponse = $this->moodleService->users()->createUser([$moodleUser]);

            Log::info('Moodle Response before validation', ['moodleResponse' => $moodleResponse]);
            if ($moodleResponse['status'] !== 'success' || !isset($moodleResponse['data'][0]['id'])) {
                Log::error('Validation failed', ['moodleResponse' => $moodleResponse]);
                throw new \Exception('Invalid Moodle API response format');
            }

            $student->moodle_id = $moodleResponse['data'][0]['id'];
            $student->save();

            Log::info('Moodle User Created', ['moodle_user' => $moodleResponse]);

            $cohortes = $this->_prepareCohortsForMoodle($student, $username);

            if (!empty($cohortes)) {
                Log::info('Adding user to cohorts', ['members' => $cohortes]);
                $cohortResponse = $this->moodleService->cohorts()->addUserToCohort($cohortes);

                if ($cohortResponse['status'] !== 'success') {
                    DB::rollBack();
                    Log::error('Error adding user to cohort in Moodle', [
                        'student_id' => $student->id,
                        'error' => $cohortResponse['message']
                    ]);
                    return response()->json([
                        'message' => 'Error adding user to cohort in Moodle FROM CONTROLLER',
                        'error' => $cohortResponse['message']
                    ], 500);
                }
            } else {
                Log::info('No cohorts to assign for student.', ['student_id' => $student->id]);
            }

            DB::commit();
            $student->load('charges');

            // Log student creation event
            StudentEvent::createEvent($student, StudentEvent::EVENT_CREATED, null, $student->toArray());

            return response()->json($student, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating student', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Added trace for better debugging
            ]);
            return response()->json([
                'message' => 'Error creating student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hard update of student data including Moodle sync.
     */
    public function hardUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:students,id',
            'email' => 'required|email|unique:students,email,' . $request->id,
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'grupo_id' => 'sometimes|nullable|exists:grupos,id',
            'semana_intensiva_id' => 'sometimes|nullable|exists:semanas_intensivas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $student = Student::findOrFail($request->id);
            
            // Capture original values before update for event logging
            $beforeData = $student->toArray();
            $oldGrupoId = $student->grupo_id;
            $oldSemanaIntensivaId = $student->semana_intensiva_id;
            $newGrupoId = $request->input('grupo_id');
            $newSemanaIntensivaId = $request->input('semana_intensiva_id');

            // Update student basic info
            $student->update($request->only(['email', 'firstname', 'lastname', 'grupo_id', 'semana_intensiva_id']));

            // Capture updated values for event logging
            $afterData = $student->fresh()->toArray();
            
            // Get changed fields
            $changedFields = [];
            foreach ($request->only(['email', 'firstname', 'lastname', 'grupo_id', 'semana_intensiva_id']) as $field => $value) {
                if ($beforeData[$field] !== $value) {
                    $changedFields[] = $field;
                }
            }

            // Ensure student has Moodle ID
            $this->ensureStudentHasMoodleId($student);

            // Sync basic info with Moodle
            $this->syncMoodleUserEmail($student);

            // Handle cohort changes if grupo or semana intensiva changed
            if ($oldGrupoId !== $newGrupoId || $oldSemanaIntensivaId !== $newSemanaIntensivaId) {
                $this->updateStudentCohorts($student, $oldGrupoId, $newGrupoId, $oldSemanaIntensivaId, $newSemanaIntensivaId);
            }

            // Log student update event
            if (!empty($changedFields)) {
                StudentEvent::createEvent($student, StudentEvent::EVENT_UPDATED, $beforeData, $afterData, implode(', ', $changedFields));
            }

            DB::commit();
            return response()->json([
                'message' => 'Student updated successfully',
                'student' => $student,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating student', [
                'student_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error updating student',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'firstname' => 'string|max:255',
            'lastname' => 'string|max:255',
            'email' => 'email',
            'phone' => 'string|max:20',
            'type' => 'in:preparatoria,facultad',
            'campus_id' => 'exists:campuses,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Capture original values before update
        $beforeData = $student->toArray();
        
        $student->update($request->all());
        
        // Capture updated values for event logging
        $afterData = $student->fresh()->toArray();
        
        // Get changed fields
        $changedFields = [];
        foreach ($request->all() as $field => $value) {
            if (array_key_exists($field, $beforeData) && $beforeData[$field] !== $value) {
                $changedFields[] = $field;
            }
        }

        // Log student update event
        if (!empty($changedFields)) {
            StudentEvent::createEvent($student, StudentEvent::EVENT_UPDATED, $beforeData, $afterData, implode(', ', $changedFields));
        }

        return response()->json($student);
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student)
    {
        return response()->json($student->load('transactions'));
    }

    /**
     * Remove the specified student and optionally delete from Moodle.
     */
    public function destroy(Student $student, Request $request)
    {
        try {
            // Capture student data before deletion for event logging
            $beforeData = $student->toArray();
            
            if ($student->moodle_id) {
                $this->moodleService->users()->deleteUser($student->moodle_id);
                Log::info('Moodle user deleted using stored moodle_id', ['student_id' => $student->id, 'moodle_id' => $student->moodle_id]);
            } else {
                $username = (string) $student->id;
                $moodleUser = $this->moodleService->users()->getUserByUsername($username);

                if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                    $userId = $moodleUser['data']['id'];
                    $this->moodleService->users()->deleteUser($userId);
                    Log::info('Moodle user deleted', ['student_id' => $student->id, 'moodle_id' => $userId]);
                } else {
                    Log::warning('Moodle user not found for deletion', ['student_id' => $student->id]);
                }
            }

            if ($request->boolean('permanent') === true) {
                // Log permanent deletion event before deleting
                StudentEvent::createEvent($student, StudentEvent::EVENT_DELETED, $beforeData, null, 'Permanent deletion');
                
                $student->forceDelete();
                return response()->json(['message' => 'Estudiante eliminado permanentemente y sincronizado con Moodle']);
            }

            // Log soft deletion event before deleting
            StudentEvent::createEvent($student, StudentEvent::EVENT_DELETED, $beforeData, null);
            
            $student->delete();
            return response()->json(['message' => 'Estudiante eliminado y sincronizado con Moodle']);
        } catch (\Exception $e) {
            Log::error('Error deleting student from Moodle', [
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'El estudiante fue eliminado de la base de datos, pero ocurrió un error al sincronizar con Moodle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove multiple students and optionally delete from Moodle.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'permanent' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $studentIds = $request->input('student_ids');
        $isPermanent = $request->boolean('permanent', false);
        $results = [
            'success' => [],
            'errors' => []
        ];

        try {
            DB::beginTransaction();
            Log::info('Starting bulk deletion operation', [
                'student_ids' => $studentIds,
                'permanent' => $isPermanent
            ]);
            // Obtener todos los estudiantes
            $students = Student::whereIn('id', $studentIds)->get();

            // Preparar IDs de Moodle para eliminación masiva
            $moodleUserIds = [];

            // Primero, obtener todos los IDs de Moodle usando el campo moodle_id cuando esté disponible
            foreach ($students as $student) {
                if ($student->moodle_id) {
                    // Si tenemos el moodle_id almacenado, usarlo directamente
                    $moodleUserIds[] = $student->moodle_id;
                } else {
                    // Si no tenemos el moodle_id, intentar buscarlo por username
                    $username = (string) $student->id;
                    $moodleUser = $this->moodleService->users()->getUserByUsername($username);

                    if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                        $moodleUserIds[] = $moodleUser['data']['id'];
                    } else {
                        Log::warning('Moodle user not found for bulk deletion', ['student_id' => $student->id]);
                    }
                }
            }

            // Eliminar usuarios de Moodle en bloque si hay IDs (primero en Moodle, luego en local)
            if (!empty($moodleUserIds)) {
                $deleteResponse = $this->moodleService->users()->deleteUser($moodleUserIds);

                if ($deleteResponse['status'] !== 'success') {
                    Log::error('Error deleting users from Moodle in bulk', [
                        'error' => $deleteResponse['message'] ?? 'Unknown error',
                        'moodle_user_ids' => $moodleUserIds
                    ]);

                    // Decidir si continuar o no basado en la política de la aplicación
                    // Aquí continuamos con la eliminación de la base de datos
                } else {
                    Log::info('Moodle users deleted in bulk', ['count' => count($moodleUserIds)]);
                }
            }

            // Después de eliminar en Moodle, eliminar estudiantes de la base de datos
            foreach ($students as $student) {
                try {
                    // Capture student data before deletion for event logging
                    $beforeData = $student->toArray();
                    
                    if ($isPermanent) {
                        // Log permanent deletion event before deleting
                        StudentEvent::createEvent($student, StudentEvent::EVENT_DELETED, $beforeData, null, 'Permanent deletion');
                        $student->forceDelete();
                    } else {
                        // Log soft deletion event before deleting
                        StudentEvent::createEvent($student, StudentEvent::EVENT_DELETED, $beforeData, null);
                        $student->delete();
                    }
                    $results['success'][] = $student->id;
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Error deleting student from database', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            $message = $isPermanent ?
                'Estudiantes eliminados permanentemente y sincronizados con Moodle' :
                'Estudiantes eliminados y sincronizados con Moodle';

            return response()->json([
                'message' => $message,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk delete operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al eliminar estudiantes en bloque',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted student.
     */
    public function restore($id)
    {
        $student = Student::withTrashed()->findOrFail($id);
        
        // Capture student data after restoration for event logging
        $student->restore();
        $afterData = $student->fresh()->toArray();
        
        // Log student restoration event
        StudentEvent::createEvent($student, StudentEvent::EVENT_RESTORED, null, $afterData);
        
        return response()->json(['message' => 'Estudiante restaurado']);
    }

    /**
     * Get students by cohort.
     */
    public function getByCohort($cohortId)
    {
        $students = Student::where('cohort_id', $cohortId)->with('cohort')->get();
        return response()->json($students);
    }

    /**
     * Sync all students with Moodle.
     */
    public function syncMoodle(Request $request)
    {
        $students = Student::get();

        if ($students->isEmpty()) {
            return response()->json(['message' => 'No students found.'], 404);
        }

        try {
            $responses = [];
            $users = $this->prepareMoodleUsersForSync($students);

            // Process in batches of 100 users
            $userChunks = array_chunk($users, 100, true);

            foreach ($userChunks as $chunk) {
                $responses[] = $this->moodleService->users()->createUser($chunk);
            }

            return response()->json($responses, 200);
        } catch (\Exception $e) {
            Log::error("Moodle Sync Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to sync with Moodle'], 500);
        }
    }

    /**
     * Export students to CSV.
     */
    public function exportCsv()
    {
        $fileName = 'students.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['username', 'email', 'grupo'];
        $students = Student::with('grupo')->get(['id', 'email', 'grupo_id']);

        $callback = function () use ($students, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->email,
                    $student->grupo ? $student->grupo->name : 'Sin grupo'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Get active students.
     */
    public function getActive()
    {
        $students = Student::where('status', 'active')->with('cohort')->get();
        return response()->json($students);
    }

    /**
     * Bulk update students' semana intensiva and sync with Moodle.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateSemanaIntensiva(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'semana_intensiva_id' => 'required|exists:semanas_intensivas,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $studentIds = $request->input('student_ids');
        $semanaIntensivaId = $request->input('semana_intensiva_id');
        $results = [
            'success' => [],
            'errors' => []
        ];

        try {
            DB::beginTransaction();
            Log::info('Starting bulk semana intensiva update operation', [
                'student_ids' => $studentIds,
                'semana_intensiva_id' => $semanaIntensivaId
            ]);

            $semanaIntensiva = SemanaIntensiva::findOrFail($semanaIntensivaId);

            $students = Student::whereIn('id', $studentIds)->get();

            foreach ($students as $student) {
                try {
                    // Capture original values before update for event logging
                    $beforeData = $student->toArray();
                    $oldSemanaIntensivaId = $student->semana_intensiva_id;
                    
                    $student->semana_intensiva_id = $semanaIntensivaId;
                    $student->save();

                    // Capture updated values for event logging
                    $afterData = $student->fresh()->toArray();

                    $username = (string) $student->id;
                    $assignResult = $this->assignToMoodleCohort($student, $semanaIntensiva, 'intensive_week', $username);

                    if ($assignResult['success']) {
                        $results['success'][] = $student->id;
                        
                        // Log semana intensiva assignment event
                        StudentEvent::createEvent(
                            $student, 
                            StudentEvent::EVENT_SEMANA_INTENSIVA_CHANGED, 
                            $beforeData, 
                            $afterData,
                            "Semana intensiva changed from {$oldSemanaIntensivaId} to {$semanaIntensivaId}"
                        );
                        
                        Log::info('Student added to intensive week cohort in Moodle', [
                            'student_id' => $student->id,
                            'semana_intensiva_id' => $semanaIntensivaId,
                            'cohort_id' => $assignResult['cohort_id'] ?? null
                        ]);
                    } else {
                        Log::warning('Failed to assign to intensive week cohort, but continuing.', [
                            'student_id' => $student->id,
                            'semana_intensiva_id' => $semanaIntensivaId,
                            'error' => $assignResult['response']['message'] ?? 'Unknown error'
                        ]);
                        $results['success'][] = $student->id;
                        
                        // Still log the event even if Moodle assignment failed
                        StudentEvent::createEvent(
                            $student, 
                            StudentEvent::EVENT_SEMANA_INTENSIVA_CHANGED, 
                            $beforeData, 
                            $afterData,
                            "Semana intensiva changed from {$oldSemanaIntensivaId} to {$semanaIntensivaId} (Moodle sync failed)"
                        );
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Error updating student semana intensiva', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Estudiantes asignados a semana intensiva y sincronizados con Moodle',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk semana intensiva update operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al asignar estudiantes a semana intensiva',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspender o activar estudiantes en Moodle.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspendStudents(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'suspended' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $studentIds = $request->input('student_ids');
        $suspended = $request->input('suspended') ? 1 : 0;
        $action = $suspended ? 'suspender' : 'activar';
        
        $results = [
            'success' => [],
            'errors' => []
        ];

        try {
            DB::beginTransaction();
            
            Log::info("Starting bulk {$action} operation for students", [
                'student_ids' => $studentIds,
                'suspended' => $suspended
            ]);

            $students = Student::whereIn('id', $studentIds)->get();
            $moodleUsers = [];

            // Preparar datos para Moodle
            foreach ($students as $student) {
                try {
                    // Asegurar que el estudiante tenga moodle_id
                    $this->ensureStudentHasMoodleId($student);
                    
                    if ($student->moodle_id) {
                        $moodleUsers[] = [
                            'id' => $student->moodle_id,
                            'suspended' => $suspended
                        ];
                        
                        // Actualizar estado local si lo deseas
                        // $student->is_active = !$suspended;
                        // $student->save();
                        
                        $results['success'][] = $student->id;
                    } else {
                        $results['errors'][] = [
                            'student_id' => $student->id,
                            'error' => 'No se pudo obtener el ID de Moodle'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Error preparing student for {$action}", [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Suspender/activar en Moodle si hay usuarios válidos
            if (!empty($moodleUsers)) {
                $moodleResponse = $this->moodleService->users()->suspendUser($moodleUsers);
                
                if ($moodleResponse['status'] !== 'success') {
                    // Si falla en Moodle, marcar todos como error
                    foreach ($results['success'] as $studentId) {
                        $results['errors'][] = [
                            'student_id' => $studentId,
                            'error' => $moodleResponse['message'] ?? 'Error en Moodle'
                        ];
                    }
                    $results['success'] = [];
                    
                    DB::rollBack();
                    
                    return response()->json([
                        'message' => "Error al {$action} estudiantes en Moodle",
                        'error' => $moodleResponse['message'] ?? 'Error desconocido',
                        'results' => $results
                    ], 500);
                }
                
                Log::info("Students successfully {$action}ed in Moodle", [
                    'count' => count($moodleUsers),
                    'action' => $action
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => "Estudiantes {$action}dos exitosamente",
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in bulk {$action} operation", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => "Error al {$action} estudiantes",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate student data.
     */
    private function validateStudent(Request $request)
    {
        return Validator::make($request->all(), [
            'firstname' => 'string|max:255',
            'lastname' => 'string|max:255',
            'email' => 'required|email|unique:students',
            'phone' => 'string|max:20',
            'type' => 'in:preparatoria,facultad',
            'campus_id' => 'required|exists:campuses,id',
        ]);
    }

    /**
     * Prepare Moodle user data for a single student.
     */
    private function prepareMoodleUser(Student $student)
    {
        return [
            "username" => (string) $student->id,
            "firstname" => strtoupper($student->firstname),
            "lastname" => strtoupper($student->lastname),
            "email" => $student->email,
            "createpassword" => true,
            "auth" => "manual",
            "idnumber" => (string) $student->id,
            "lang" => "es_mx",
            "calendartype" => "gregorian",
            "timezone" => "America/Mexico_City"
        ];
    }

    /**
     * Prepare Moodle users data for bulk sync.
     */
    private function prepareMoodleUsersForSync($students)
    {
        $users = [];

        foreach ($students as $student) {
            $users["user_{$student->id}"] = $this->prepareMoodleUser($student);
        }

        return $users;
    }

    /**
     * Sync student email with Moodle.
     */
    private function syncMoodleUserEmail(Student $student)
    {
        $result = $this->moodleService->users()->getUserByUsername($student->id);

        if ($result['status'] === 'success') {
            $user = $result['data'];
            $this->moodleService->users()->updateUser([[
                "id" => $user['id'],
                "email" => $student->email,
                "firstname" => $student->firstname,
                "lastname" => $student->lastname
            ]]);
        }

        return $result;
    }

    /**
     * Prepare the list of cohorts to assign a student to in Moodle.
     *
     * @param Student $student The student instance.
     * @param string $username The Moodle username (student ID).
     * @return array The array of cohort assignments for the Moodle API.
     */
    private function _prepareCohortsForMoodle(Student $student, string $username): array
    {
        $cohortes = [];
        $student->loadMissing(['grupo', 'semana_intensiva', 'carrera.modulos']);

        if ($student->grupo && $student->grupo->moodle_id) {
            Log::info('Grupo found for cohort assignment', ['grupo' => $student->grupo]);
            $cohortes[] = [
                'cohorttype' => ['type' => 'id', 'value' => $student->grupo->moodle_id],
                'usertype' => ['type' => 'username', 'value' => $username]
            ];
        } elseif ($student->grupo_id) {
            Log::warning('Group assigned but Moodle ID missing', ['grupo_id' => $student->grupo_id]);
        }

        if ($student->semana_intensiva && $student->semana_intensiva->moodle_id) {
            Log::info('Semana intensiva found for cohort assignment', ['semana_intensiva' => $student->semana_intensiva]);
            $cohortes[] = [
                'cohorttype' => ['type' => 'id', 'value' => $student->semana_intensiva->moodle_id],
                'usertype' => ['type' => 'username', 'value' => $username]
            ];
        } elseif ($student->semana_intensiva_id) {
            Log::warning('Intensive week assigned but Moodle ID missing', ['semana_intensiva_id' => $student->semana_intensiva_id]);
        }

        // Add Career Module Cohorts
        if ($student->carrera && $student->carrera->modulos) {
            Log::info('Carrera found, checking modules for cohort assignment', ['carrera' => $student->carrera->name, 'modulos_count' => $student->carrera->modulos->count()]);
            foreach ($student->carrera->modulos as $modulo) {
                if ($modulo->moodle_id) {
                    Log::info('Adding module cohort', ['modulo_name' => $modulo->name, 'moodle_id' => $modulo->moodle_id]);
                    $cohortes[] = [
                        'cohorttype' => ['type' => 'id', 'value' => $modulo->moodle_id],
                        'usertype' => ['type' => 'username', 'value' => $username]
                    ];
                } else {
                    Log::warning('Module found but Moodle ID missing', ['modulo_name' => $modulo->name, 'modulo_id' => $modulo->id]);
                }
            }
        }

        return $cohortes;
    }

    /**
     * Assign student to a Moodle cohort based on group or intensive week.
     *
     * @param Student $student The student instance.
     * @param mixed $relatedModel The Grupo or SemanaIntensiva model instance.
     * @param string $type Type of cohort ('group' or 'intensive_week').
     * @param string $username Moodle username (student ID).
     * @return array ['success' => bool, 'response' => array|null, 'cohort_id' => int|null]
     */
    private function assignToMoodleCohort(Student $student, $relatedModel, string $type, string $username): array
    {
        // This function might need review or removal if _prepareCohortsForMoodle covers all cases in store/update
        // Keeping it for now as it's used in hardUpdate
        if (!$student->period) {
            // hardUpdate might not load period, ensure it's loaded if needed or adjust logic
            $student->load('period');
            if (!$student->period) {
                Log::error('Student period not found for cohort assignment', ['student_id' => $student->id]);
                return ['success' => false, 'response' => ['message' => 'Student period not found'], 'cohort_id' => null];
            }
        }

        // Determine cohort name based on type - This logic might be flawed if period name isn't the prefix
        // Consider storing cohort names or using a more robust mapping if needed.
        $cohortName = $student->period->name . $relatedModel->name; // Potential issue: Assumes period name is part of cohort name
        $cohortId = $relatedModel->moodle_id; // Prefer using stored moodle_id if available

        if (!$cohortId) {
            // Fallback to fetching by name if moodle_id is missing
            Log::info('Moodle ID missing for related model, attempting to fetch by name', ['type' => $type, 'related_model_id' => $relatedModel->id, 'cohort_name' => $cohortName]);
            $cohortId = $this->moodleService->cohorts()->getCohortIdByName($cohortName);
            if ($cohortId) {
                // Optionally update the related model with the found moodle_id
                // $relatedModel->update(['moodle_id' => $cohortId]);
                Log::info('Found cohort ID by name', ['cohort_id' => $cohortId]);
            } else {
                $logMessage = $type === 'group' ? 'Group cohort not found in Moodle by name' : 'Intensive week cohort not found in Moodle by name';
                Log::warning($logMessage, [
                    'student_id' => $student->id,
                    'related_model_id' => $relatedModel->id,
                    'cohort_name' => $cohortName
                ]);
                // For groups, cohort might be mandatory. For intensive weeks, maybe optional.
                if ($type === 'group') {
                    return ['success' => false, 'response' => ['message' => $logMessage], 'cohort_id' => null];
                }
                // If optional, consider it a 'success' in terms of not blocking the update.
                return ['success' => true, 'response' => null, 'cohort_id' => null];
            }
        }

        $cohortAssignment = [
            [
                'cohorttype' => ['type' => 'id', 'value' => $cohortId],
                'usertype' => ['type' => 'username', 'value' => $username]
            ]
        ];

        // Add user to cohort
        Log::info('Assigning user to cohort', ['assignment' => $cohortAssignment]);
        $cohortResponse = $this->moodleService->cohorts()->addUserToCohort($cohortAssignment);

        if ($cohortResponse['status'] !== 'success') {
            $errorMessage = $type === 'group'
                ? 'Error adding user to group cohort in Moodle'
                : 'Error adding user to intensive week cohort in Moodle';
            Log::error($errorMessage, [
                'student_id' => $student->id,
                'cohort_id' => $cohortId,
                'moodle_error' => $cohortResponse['message'] ?? 'Unknown Moodle error'
            ]);
            return [
                'success' => false,
                'response' => ['message' => $errorMessage, 'error' => $cohortResponse['message'] ?? 'Unknown Moodle error'],
                'cohort_id' => $cohortId
            ];
        }

        Log::info('Successfully assigned user to cohort', ['student_id' => $student->id, 'cohort_id' => $cohortId]);
        return ['success' => true, 'response' => null, 'cohort_id' => $cohortId];
    }

    /**
     * Sincroniza todos los estudiantes con sus respectivos módulos en Moodle.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncStudentModules(Request $request)
    {
        try {
            DB::beginTransaction();

            $students = Student::with(['grupo', 'semana_intensiva', 'carrera.modulos'])
                ->get();
            if ($students->isEmpty()) {
                return response()->json(['message' => 'No se encontraron estudiantes activos.'], 404);
            }

            $results = [
                'success' => [],
                'errors' => []
            ];

            $totalStudents = $students->count();
            $processedCount = 0;

            foreach ($students as $student) {
                try {
                    $username = (string) $student->id;
                    $moodleUser = $this->moodleService->users()->getUserByUsername($username);
                    if ($moodleUser['status'] !== 'success' || !isset($moodleUser['data']['id'])) {
                        Log::warning('Estudiante no encontrado en Moodle para sincronización de módulos', ['student_id' => $student->id]);
                        $results['errors'][] = [
                            'student_id' => $student->id,
                            'error' => 'Estudiante no encontrado en Moodle'
                        ];
                        continue;
                    }
                    $cohortes = $this->_prepareCohortsForMoodle($student, $username);
                    if (empty($cohortes)) {
                        Log::info('No hay cohortes para asignar al estudiante.', ['student_id' => $student->id]);
                        $results['success'][] = [
                            'student_id' => $student->id,
                            'message' => 'No hay cohortes para asignar'
                        ];
                        continue;
                    }
                    $cohortResponse = $this->moodleService->cohorts()->addUserToCohort($cohortes);
                    if ($cohortResponse['status'] !== 'success') {
                        Log::error('Error al asignar estudiante a cohortes en Moodle', [
                            'student_id' => $student->id,
                            'error' => $cohortResponse['message'] ?? 'Error desconocido'
                        ]);
                        $results['errors'][] = [
                            'student_id' => $student->id,
                            'error' => $cohortResponse['message'] ?? 'Error desconocido'
                        ];
                    } else {
                        Log::info('Estudiante sincronizado correctamente con sus módulos en Moodle', [
                            'student_id' => $student->id,
                            'cohortes_count' => count($cohortes)
                        ]);
                        $results['success'][] = $student->id;
                    }

                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error('Error al sincronizar estudiante con módulos', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Sincronización de estudiantes con módulos completada',
                'total_students' => $totalStudents,
                'processed_students' => $processedCount,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en la operación de sincronización de módulos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al sincronizar estudiantes con módulos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ensure student has a Moodle ID, fetch and save if missing.
     */
    private function ensureStudentHasMoodleId(Student $student): void
    {
        if (!$student->moodle_id) {
            $username = (string) $student->id;
            $moodleUser = $this->moodleService->users()->getUserByUsername($username);
            
            if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                $student->moodle_id = $moodleUser['data']['id'];
                $student->save();
                
                Log::info('Moodle ID fetched and saved for student', [
                    'student_id' => $student->id,
                    'moodle_id' => $student->moodle_id
                ]);
            } else {
                throw new \Exception('Failed to fetch Moodle ID for student: ' . $student->id);
            }
        }
    }

    /**
     * Update student cohort assignments based on grupo and semana intensiva changes.
     */
    private function updateStudentCohorts(
        Student $student, 
        ?int $oldGrupoId, 
        ?int $newGrupoId, 
        ?int $oldSemanaIntensivaId, 
        ?int $newSemanaIntensivaId
    ): void {
        $cohortsToRemove = $this->prepareCohortsToRemove($student, $oldGrupoId, $oldSemanaIntensivaId);
        $cohortsToAdd = $this->prepareCohortsToAdd($student, $newGrupoId, $newSemanaIntensivaId);

        // Remove from old cohorts first
        if (!empty($cohortsToRemove)) {
            $this->removeStudentFromCohorts($student, $cohortsToRemove);
        }

        // Add to new cohorts
        if (!empty($cohortsToAdd)) {
            $this->addStudentToCohorts($student, $cohortsToAdd);
        }
    }

    /**
     * Prepare cohorts to remove student from.
     */
    private function prepareCohortsToRemove(Student $student, ?int $oldGrupoId, ?int $oldSemanaIntensivaId): array
    {
        $cohortsToRemove = [];

        // Remove from old grupo cohort
        if ($oldGrupoId) {
            $oldGrupo = Grupo::select('id', 'moodle_id', 'name')->find($oldGrupoId);
            if ($oldGrupo && $oldGrupo->moodle_id) {
                $cohortsToRemove[] = [
                    'userid' => $student->moodle_id,
                    'cohortid' => $oldGrupo->moodle_id
                ];
                
                Log::info('Preparing to remove student from old group cohort', [
                    'student_id' => $student->id,
                    'moodle_user_id' => $student->moodle_id,
                    'old_grupo_id' => $oldGrupoId,
                    'old_cohort_id' => $oldGrupo->moodle_id,
                    'grupo_name' => $oldGrupo->name
                ]);
            }
        }

        // Remove from old semana intensiva cohort
        if ($oldSemanaIntensivaId) {
            $oldSemanaIntensiva = SemanaIntensiva::select('id', 'moodle_id', 'name')->find($oldSemanaIntensivaId);
            if ($oldSemanaIntensiva && $oldSemanaIntensiva->moodle_id) {
                $cohortsToRemove[] = [
                    'userid' => $student->moodle_id,
                    'cohortid' => $oldSemanaIntensiva->moodle_id
                ];
                
                Log::info('Preparing to remove student from old intensive week cohort', [
                    'student_id' => $student->id,
                    'moodle_user_id' => $student->moodle_id,
                    'old_semana_intensiva_id' => $oldSemanaIntensivaId,
                    'old_cohort_id' => $oldSemanaIntensiva->moodle_id,
                    'semana_name' => $oldSemanaIntensiva->name
                ]);
            }
        }

        return $cohortsToRemove;
    }

    /**
     * Prepare cohorts to add student to.
     */
    private function prepareCohortsToAdd(Student $student, ?int $newGrupoId, ?int $newSemanaIntensivaId): array
    {
        $cohortsToAdd = [];

        // Add to new grupo cohort using the format that works with addUserToCohort
        if ($newGrupoId) {
            $newGrupo = Grupo::select('id', 'moodle_id', 'name')->find($newGrupoId);
            if ($newGrupo && $newGrupo->moodle_id) {
                $cohortsToAdd[] = [
                    'cohorttype' => ['type' => 'id', 'value' => $newGrupo->moodle_id],
                    'usertype' => ['type' => 'username', 'value' => (string) $student->id]
                ];
                
                Log::info('Preparing to add student to new group cohort', [
                    'student_id' => $student->id,
                    'moodle_user_id' => $student->moodle_id,
                    'new_grupo_id' => $newGrupoId,
                    'new_cohort_id' => $newGrupo->moodle_id,
                    'grupo_name' => $newGrupo->name
                ]);
            }
        }

        // Add to new semana intensiva cohort using the format that works with addUserToCohort
        if ($newSemanaIntensivaId) {
            $newSemanaIntensiva = SemanaIntensiva::select('id', 'moodle_id', 'name')->find($newSemanaIntensivaId);
            if ($newSemanaIntensiva && $newSemanaIntensiva->moodle_id) {
                $cohortsToAdd[] = [
                    'cohorttype' => ['type' => 'id', 'value' => $newSemanaIntensiva->moodle_id],
                    'usertype' => ['type' => 'username', 'value' => (string) $student->id]
                ];
                
                Log::info('Preparing to add student to new intensive week cohort', [
                    'student_id' => $student->id,
                    'moodle_user_id' => $student->moodle_id,
                    'new_semana_intensiva_id' => $newSemanaIntensivaId,
                    'new_cohort_id' => $newSemanaIntensiva->moodle_id,
                    'semana_name' => $newSemanaIntensiva->name
                ]);
            }
        }

        return $cohortsToAdd;
    }

    /**
     * Remove student from specified cohorts.
     */
    private function removeStudentFromCohorts(Student $student, array $cohortsToRemove): void
    {
        Log::info('Removing student from cohorts', [
            'student_id' => $student->id,
            'moodle_id' => $student->moodle_id,
            'cohorts_count' => count($cohortsToRemove),
            'cohorts' => $cohortsToRemove
        ]);

        $removeResult = $this->moodleService->cohorts()->removeUsersFromCohorts($cohortsToRemove);

        if ($removeResult['status'] !== 'success') {
            Log::error('Failed to remove student from cohorts', [
                'student_id' => $student->id,
                'moodle_id' => $student->moodle_id,
                'error' => $removeResult['message'] ?? 'Unknown error',
                'cohorts' => $cohortsToRemove
            ]);
            // Continue anyway - removal failure shouldn't block the update
        } else {
            Log::info('Successfully removed student from cohorts', [
                'student_id' => $student->id,
                'moodle_id' => $student->moodle_id,
                'cohorts_removed' => count($cohortsToRemove)
            ]);
        }
    }

    /**
     * Add student to specified cohorts.
     */
    private function addStudentToCohorts(Student $student, array $cohortsToAdd): void
    {
        Log::info('Adding student to cohorts', [
            'student_id' => $student->id,
            'moodle_id' => $student->moodle_id,
            'cohorts_count' => count($cohortsToAdd),
            'cohorts' => $cohortsToAdd
        ]);

        $addResult = $this->moodleService->cohorts()->addUserToCohort($cohortsToAdd);

        if ($addResult['status'] !== 'success') {
            Log::error('Failed to add student to cohorts', [
                'student_id' => $student->id,
                'moodle_id' => $student->moodle_id,
                'error' => $addResult['message'] ?? 'Unknown error',
                'cohorts' => $cohortsToAdd,
                'moodle_response' => $addResult
            ]);
            
            throw new \Exception('Failed to add student to new cohorts in Moodle: ' . ($addResult['message'] ?? 'Unknown error'));
        } else {
            // Check for warnings in the response
            if (isset($addResult['data']['warnings']) && !empty($addResult['data']['warnings'])) {
                Log::warning('Student added to cohorts but with warnings', [
                    'student_id' => $student->id,
                    'moodle_id' => $student->moodle_id,
                    'warnings' => $addResult['data']['warnings'],
                    'cohorts_attempted' => count($cohortsToAdd)
                ]);
            } else {
                Log::info('Successfully added student to cohorts', [
                    'student_id' => $student->id,
                    'moodle_id' => $student->moodle_id,
                    'cohorts_added' => count($cohortsToAdd)
                ]);
            }
        }
    }

    /**
     * Bulk mark students as inactive.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkMarkAsInactive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $studentIds = $request->input('student_ids');
        $results = [
            'success' => [],
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            $students = Student::whereIn('id', $studentIds)->get();

            foreach ($students as $student) {
                try {
                    // Capture data before update for event logging
                    $beforeData = $student->toArray();
                    
                    $student->update(['status' => 'Inactivo']);
                    
                    // Capture data after update for event logging
                    $afterData = $student->fresh()->toArray();
                    
                    // Log student status change event
                    StudentEvent::createEvent(
                        $student, 
                        StudentEvent::EVENT_UPDATED, 
                        $beforeData, 
                        $afterData,
                        'Student status changed to Inactive'
                    );
                    
                    $results['success'][] = $student->id;
                    
                    Log::info('Student marked as inactive', [
                        'student_id' => $student->id,
                        'user_id' => auth()->id()
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Error marking student as inactive', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Estudiantes marcados como inactivos correctamente',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk mark as inactive operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al marcar estudiantes como inactivos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk mark students as active.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkMarkAsActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $studentIds = $request->input('student_ids');
        $results = [
            'success' => [],
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            $students = Student::whereIn('id', $studentIds)->get();

            foreach ($students as $student) {
                try {
                    // Capture data before update for event logging
                    $beforeData = $student->toArray();
                    
                    $student->update(['status' => 'Activo']);
                    
                    // Capture data after update for event logging
                    $afterData = $student->fresh()->toArray();
                    
                    // Log student status change event
                    StudentEvent::createEvent(
                        $student, 
                        StudentEvent::EVENT_UPDATED, 
                        $beforeData, 
                        $afterData,
                        'Student status changed to Active'
                    );
                    
                    $results['success'][] = $student->id;
                    
                    Log::info('Student marked as active', [
                        'student_id' => $student->id,
                        'user_id' => auth()->id()
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Error marking student as active', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Estudiantes marcados como activos correctamente',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk mark as active operation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al marcar estudiantes como activos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspender o activar un estudiante individual en Moodle.
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspendStudent(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'suspended' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $suspended = $request->input('suspended') ? 1 : 0;
        $action = $suspended ? 'suspender' : 'activar';

        try {
            DB::beginTransaction();
            
            $student = Student::findOrFail($id);
            
            Log::info("Starting {$action} operation for student", [
                'student_id' => $id,
                'suspended' => $suspended
            ]);

            // Asegurar que el estudiante tenga moodle_id
            $this->ensureStudentHasMoodleId($student);
            
            if (!$student->moodle_id) {
                return response()->json([
                    'message' => 'No se pudo obtener el ID de Moodle del estudiante',
                    'error' => 'Moodle ID not found'
                ], 400);
            }

            // Suspender/activar en Moodle
            $moodleUsers = [[
                'id' => $student->moodle_id,
                'suspended' => $suspended
            ]];

            $moodleResponse = $this->moodleService->users()->suspendUser($moodleUsers);
            
            if ($moodleResponse['status'] !== 'success') {
                DB::rollBack();
                
                return response()->json([
                    'message' => "Error al {$action} estudiante en Moodle",
                    'error' => $moodleResponse['message'] ?? 'Error desconocido'
                ], 500);
            }

            // Actualizar estado local si lo deseas (opcional)
            // $student->is_active = !$suspended;
            // $student->save();

            DB::commit();

            Log::info("Student successfully {$action}ed in Moodle", [
                'student_id' => $id,
                'action' => $action
            ]);

            return response()->json([
                'message' => "Estudiante {$action}do exitosamente",
                'student' => $student
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in {$action} operation", [
                'student_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => "Error al {$action} estudiante",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
