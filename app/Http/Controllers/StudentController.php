<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Modulo;
use App\Models\Promocion;
use App\Models\SemanaIntensiva;
use App\Models\Student;
use App\Models\Transaction;
use App\Services\Moodle;
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

    public function __construct(Moodle $moodleService)
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
            'grupo'=>$grupo
        ]);

        $query = Student::with(['period', 'transactions', 'municipio', 'prepa', 'facultad', 'carrera', 'grupo']);

        if ($campus_id) {
            $query->where('campus_id', $campus_id);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                  ->orWhere('lastname', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($searchPhone) {
            $query->where('phone', 'LIKE', "%{$searchPhone}%");
        }

        if ($searchMatricula) {
            $query->where('matricula', 'LIKE', "%{$searchMatricula}%");
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

        $students = $query->paginate($perPage, ['*'], 'page', $page);

        foreach($students as $student) {
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
            $moodleResponse = $this->moodleService->createUser([$moodleUser]);

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
                $cohortResponse = $this->moodleService->addUserToCohort($cohortes);

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
            $oldGrupoId = $student->grupo_id;
            $newGrupoId = $request->input('grupo_id');
            $newSemanaIntensivaId = $request->input('semana_intensiva_id');

            // Update student basic info, including semana_intensiva_id
            $student->update($request->only(['email', 'firstname', 'lastname', 'grupo_id', 'semana_intensiva_id']));

            // Sync email and name with Moodle
            $this->syncMoodleUserEmail($student);

            // Obtener el usuario de Moodle
            $username = (string) $student->id;
            $moodleUser = $this->moodleService->getUserByUsername($username);

            if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                $userId = $moodleUser['data']['id'];

                // Assign to new group cohort if specified
                if ($newGrupoId) {
                    $newGrupo = Grupo::find($newGrupoId);
                    if ($newGrupo) {
                        $assignResult = $this->assignToMoodleCohort($student, $newGrupo, 'group', $username);
                        if (!$assignResult['success']) {
                            DB::rollBack();
                            return response()->json($assignResult['response'], 500);
                        }
                        Log::info('Student group cohort updated in Moodle', [
                            'student_id' => $student->id,
                            'old_grupo_id' => $oldGrupoId,
                            'new_grupo_id' => $newGrupoId,
                            'cohort_id' => $assignResult['cohort_id'] ?? null
                        ]);
                    }
                }

                // Assign to new intensive week cohort if specified
                if ($newSemanaIntensivaId) {
                    $newSemanaIntensiva = SemanaIntensiva::find($newSemanaIntensivaId);
                    if ($newSemanaIntensiva) {
                        $assignResult = $this->assignToMoodleCohort($student, $newSemanaIntensiva, 'intensive_week', $username);
                        if (!$assignResult['success']) {
                            // Decide if rollback is necessary or just log warning
                            Log::warning('Failed to assign to intensive week cohort, but continuing.', [
                                'student_id' => $student->id,
                                'semana_intensiva_id' => $newSemanaIntensivaId,
                                'error' => $assignResult['response']['message'] ?? 'Unknown error'
                            ]);
                            // Optionally rollback: DB::rollBack(); return response()->json($assignResult['response'], 500);
                        } else {
                            Log::info('Student added to intensive week cohort in Moodle', [
                                'student_id' => $student->id,
                                'semana_intensiva_id' => $newSemanaIntensivaId,
                                'cohort_id' => $assignResult['cohort_id'] ?? null
                            ]);
                        }
                    }
                }
            } else {
                Log::warning('Moodle user not found for cohort update', ['student_id' => $student->id]);
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
                'error' => $e->getMessage()
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

        $student->update($request->all());

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
            if ($student->moodle_id) {
                $this->moodleService->deleteUser($student->moodle_id);
                Log::info('Moodle user deleted using stored moodle_id', ['student_id' => $student->id, 'moodle_id' => $student->moodle_id]);
            } else {
                $username = (string) $student->id;
                $moodleUser = $this->moodleService->getUserByUsername($username);

                if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                    $userId = $moodleUser['data']['id'];
                    $this->moodleService->deleteUser($userId);
                    Log::info('Moodle user deleted', ['student_id' => $student->id, 'moodle_id' => $userId]);
                } else {
                    Log::warning('Moodle user not found for deletion', ['student_id' => $student->id]);
                }
            }

            if ($request->boolean('permanent') === true) {
                $student->forceDelete();
                return response()->json(['message' => 'Estudiante eliminado permanentemente y sincronizado con Moodle']);
            }

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
                    $moodleUser = $this->moodleService->getUserByUsername($username);
                    
                    if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                        $moodleUserIds[] = $moodleUser['data']['id'];
                    } else {
                        Log::warning('Moodle user not found for bulk deletion', ['student_id' => $student->id]);
                    }
                }
            }
            
            // Eliminar usuarios de Moodle en bloque si hay IDs (primero en Moodle, luego en local)
            if (!empty($moodleUserIds)) {
                $deleteResponse = $this->moodleService->deleteUser($moodleUserIds);
                
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
                    if ($isPermanent) {
                        $student->forceDelete();
                    } else {
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
        $student->restore();
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
                $responses[] = $this->moodleService->createUser($chunk);
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
                    $student->semana_intensiva_id = $semanaIntensivaId;
                    $student->save();
                    
                    $username = (string) $student->id;
                    $assignResult = $this->assignToMoodleCohort($student, $semanaIntensiva, 'intensive_week', $username);
                    
                    if ($assignResult['success']) {
                        $results['success'][] = $student->id;
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
        $result = $this->moodleService->getUserByUsername($student->id);

        if ($result['status'] === 'success') {
            $user = $result['data'];
            $this->moodleService->updateUser([[
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
            $cohortId = $this->moodleService->getCohortIdByName($cohortName);
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
        $cohortResponse = $this->moodleService->addUserToCohort($cohortAssignment);

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
}
