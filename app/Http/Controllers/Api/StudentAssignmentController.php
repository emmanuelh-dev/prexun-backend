<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentAssignment;
use App\Models\Student;
use App\Models\Period;
use App\Models\Grupo;
use App\Models\SemanaIntensiva;
use App\Models\Carrera;
use App\Services\Moodle\MoodleService;
use App\Services\StudentGradesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StudentAssignmentController extends Controller
{
    protected $moodleService;
    protected $gradesService;

    public function __construct(MoodleService $moodleService, StudentGradesService $gradesService)
    {
        $this->moodleService = $moodleService;
        $this->gradesService = $gradesService;
    }

    /**
     * Display a listing of student assignments.
     */
    public function index(Request $request)
    {
        $query = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera']);

        // Apply filters
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }

        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }

        if ($request->has('semana_intensiva_id')) {
            $query->where('semana_intensiva_id', $request->semana_intensiva_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('book_delivery_type')) {
            $query->where('book_delivery_type', $request->book_delivery_type);
        }

        if ($request->has('book_delivered')) {
            $query->where('book_delivered', $request->boolean('book_delivered'));
        }

        // Apply scopes
        if ($request->has('only_active') && $request->boolean('only_active')) {
            $query->active();
        }

        if ($request->has('only_current') && $request->boolean('only_current')) {
            $query->current();
        }

        $perPage = (int) $request->query('per_page', 15);
        $page = (int) $request->query('page', 1);

        $assignments = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($assignments);
    }

    /**
     * Store a newly created student assignment.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'period_id' => 'nullable|exists:periods,id',
            'grupo_id' => 'nullable|exists:grupos,id',
            'semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'carrer_id' => 'nullable|exists:carreers,id',
            'assigned_at' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:assigned_at',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
            'book_delivered' => 'boolean',
            'book_delivery_type' => 'nullable|in:digital,fisico,paqueteria',
            'book_delivery_date' => 'nullable|date',
            'book_notes' => 'nullable|string|max:1000',
            'book_modulos' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
            'book_general' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $validated['assigned_at'] = $validated['assigned_at'] ?? now();
        $validated['is_active'] = $validated['is_active'] ?? true;

        return DB::transaction(function () use ($validated) {
            $assignment = StudentAssignment::create($validated);

            // Sync with Moodle
            $this->syncAssignmentWithMoodle($assignment);

            return response()->json(
                $assignment->load(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera']),
                201
            );
        });
    }

    /**
     * Display the specified student assignment.
     */
    public function show($id)
    {
        $assignment = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
            ->findOrFail($id);

        return response()->json($assignment);
    }

    /**
     * Update the specified student assignment.
     */
    public function update(Request $request, $id)
    {
        $assignment = StudentAssignment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'student_id' => 'sometimes|exists:students,id',
            'period_id' => 'nullable|exists:periods,id',
            'grupo_id' => 'nullable|exists:grupos,id',
            'semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'carrer_id' => 'nullable|exists:carreers,id',
            'assigned_at' => 'sometimes|date',
            'valid_until' => 'nullable|date|after_or_equal:assigned_at',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
            'book_delivered' => 'boolean',
            'book_delivery_type' => 'nullable|in:digital,fisico,paqueteria',
            'book_delivery_date' => 'nullable|date',
            'book_notes' => 'nullable|string|max:1000',
            'book_modulos' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
            'book_general' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($assignment, $validator) {
            $oldGrupoId = $assignment->grupo_id;
            $oldSemanaIntensivaId = $assignment->semana_intensiva_id;
            $oldCarreraId = $assignment->carrer_id;

            $assignment->update($validator->validated());

            $newGrupoId = $assignment->grupo_id;
            $newSemanaIntensivaId = $assignment->semana_intensiva_id;
            $newCarreraId = $assignment->carrer_id;

            if ($oldGrupoId !== $newGrupoId || $oldSemanaIntensivaId !== $newSemanaIntensivaId || $oldCarreraId !== $newCarreraId) {
                $this->updateMoodleCohorts($assignment, $oldGrupoId, $newGrupoId, $oldSemanaIntensivaId, $newSemanaIntensivaId, $oldCarreraId, $newCarreraId);
            }

            return response()->json(
                $assignment->fresh(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
            );
        });
    }

    /**
     * Remove the specified student assignment.
     */
    public function destroy($id)
    {
        $assignment = StudentAssignment::findOrFail($id);

        return DB::transaction(function () use ($assignment) {
            // Remove student from Moodle before deleting record
            $this->removeAssignmentFromMoodle($assignment);
            $assignment->delete();

            return response()->json([
                'message' => 'Asignación eliminada correctamente'
            ]);
        });
    }

    public function getByStudent($studentId)
    {
        $assignments = StudentAssignment::with(['period', 'grupo', 'semanaIntensiva', 'carrera'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($assignments);
    }

    public function getByPeriod($periodId)
    {
        $assignments = StudentAssignment::with(['student', 'grupo', 'semanaIntensiva'])
            ->where('period_id', $periodId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($assignments);
    }

    public function getByGrupo($grupoId)
    {
        $assignments = StudentAssignment::with(['student', 'period', 'semanaIntensiva'])
            ->where('grupo_id', $grupoId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($assignments);
    }

    public function getBySemanaIntensiva($semanaIntensivaId)
    {
        $assignments = StudentAssignment::with(['student', 'period', 'grupo'])
            ->where('semana_intensiva_id', $semanaIntensivaId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($assignments);
    }

    /**
     * Bulk create assignments.
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.student_id' => 'required|exists:students,id',
            'assignments.*.period_id' => 'nullable|exists:periods,id',
            'assignments.*.grupo_id' => 'nullable|exists:grupos,id',
            'assignments.*.semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'assignments.*.carrer_id' => 'nullable|exists:carreers,id',
            'assignments.*.assigned_at' => 'nullable|date',
            'assignments.*.valid_until' => 'nullable|date',
            'assignments.*.is_active' => 'boolean',
            'assignments.*.notes' => 'nullable|string|max:1000',
            'assignments.*.book_modulos' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
            'assignments.*.book_general' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $assignments = [];
            $now = now();

            foreach ($request->assignments as $assignmentData) {
                $assignmentData['assigned_at'] = $assignmentData['assigned_at'] ?? $now;
                $assignmentData['is_active'] = $assignmentData['is_active'] ?? true;

                $assignment = StudentAssignment::create($assignmentData);
                $assignments[] = $assignment->load(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera']);

                $this->syncAssignmentWithMoodle($assignment);
            }

            return response()->json([
                'message' => 'Asignaciones creadas correctamente',
                'assignments' => $assignments
            ], 201);
        });
    }

    /**
     * Bulk update assignments.
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'exists:student_assignments,id',
            'updates' => 'required|array',
            'updates.period_id' => 'nullable|exists:periods,id',
            'updates.grupo_id' => 'nullable|exists:grupos,id',
            'updates.semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'updates.carrer_id' => 'nullable|exists:carreers,id',
            'updates.valid_until' => 'nullable|date',
            'updates.is_active' => 'sometimes|boolean',
            'updates.notes' => 'nullable|string|max:1000',
            'updates.book_modulos' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
            'updates.book_general' => 'nullable|in:no entregado,paqueteria,en fisico,digital',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $assignmentIds = $request->assignment_ids;
            $updates = $request->updates;

            $assignmentsBeforeUpdate = StudentAssignment::whereIn('id', $assignmentIds)
                ->select('id', 'student_id', 'grupo_id', 'semana_intensiva_id', 'carrer_id')
                ->get()
                ->keyBy('id');

            StudentAssignment::whereIn('id', $assignmentIds)->update($updates);

            $updatedAssignments = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
                ->whereIn('id', $assignmentIds)
                ->get();

            if (isset($updates['grupo_id']) || isset($updates['semana_intensiva_id']) || isset($updates['carrer_id'])) {
                foreach ($updatedAssignments as $assignment) {
                    $oldAssignment = $assignmentsBeforeUpdate->get($assignment->id);
                    if ($oldAssignment) {
                        $this->updateMoodleCohorts(
                            $assignment,
                            $oldAssignment->grupo_id,
                            $assignment->grupo_id,
                            $oldAssignment->semana_intensiva_id,
                            $assignment->semana_intensiva_id,
                            $oldAssignment->carrer_id,
                            $assignment->carrer_id
                        );
                    }
                }
            }

            return response()->json([
                'message' => 'Asignaciones actualizadas correctamente',
                'assignments' => $updatedAssignments
            ]);
        });
    }

    /**
     * Toggle active status for one assignment (by route id) or multiple assignments.
     */
    public function toggleActive(Request $request, $id = null)
    {
        if ($id) {
            $assignment = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
                ->findOrFail($id);

            $assignment->update(['is_active' => !$assignment->is_active]);

            return response()->json([
                'message' => 'Estado de asignación actualizado correctamente',
                'assignment' => $assignment->fresh(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
            ]);
        }

        $validator = Validator::make($request->all(), [
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'exists:student_assignments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignmentIds = $request->assignment_ids;
        $assignments = StudentAssignment::whereIn('id', $assignmentIds)->get();

        foreach ($assignments as $assignment) {
            $assignment->update(['is_active' => !$assignment->is_active]);
        }

        $updatedAssignments = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
            ->whereIn('id', $assignmentIds)
            ->get();

        return response()->json([
            'message' => 'Estado de asignaciones actualizado correctamente',
            'assignments' => $updatedAssignments
        ]);
    }

    /**
     * Get students that have active assignments in a specific period.
     * OPTIMIZED: Using Eager Loading to avoid N+1 issues.
     */
    public function getStudentsByAssignedPeriod(Request $request, $periodId)
    {
        $campus_id = $request->get('campus_id');
        $search = $request->get('search');
        $searchDate = $request->get('searchDate');
        $searchPhone = $request->get('searchPhone');
        $searchMatricula = $request->get('searchMatricula');
        $grupo = $request->get('grupo');
        $semanaIntensivaFilter = $request->get('semanaIntensivaFilter');
        $perPage = $request->get('perPage', 10);
        $page = $request->get('page', 1);

        Log::info('Fetching students by assigned period', ['period_id' => $periodId]);

        // Optimized query with eager loading to prevent dozens of database hits
        $query = Student::with([
            'period',
            'transactions',
            'municipio',
            'prepa',
            'facultad',
            'carrera',
            'grupo',
            'assignments' => function ($q) use ($periodId) {
                $q->where('period_id', $periodId)
                    ->where('is_active', true)
                    ->with(['grupo', 'semanaIntensiva', 'carrera']);
            }
        ])
            ->whereHas('assignments', function ($q) use ($periodId) {
                $q->where('period_id', $periodId)->where('is_active', true);
            });

        if ($campus_id)
            $query->where('campus_id', $campus_id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($searchPhone)
            $query->where('phone', 'LIKE', "%{$searchPhone}%");
        if ($searchMatricula)
            $query->where('id', 'LIKE', "%{$searchMatricula}%");
        if ($searchDate)
            $query->whereDate('created_at', $searchDate);

        if ($grupo) {
            $query->where(function ($q) use ($grupo, $periodId) {
                $q->where('grupo_id', $grupo)
                    ->orWhereHas('assignments', function ($subQ) use ($grupo, $periodId) {
                        $subQ->where('grupo_id', $grupo)->where('period_id', $periodId)->where('is_active', true);
                    });
            });
        }

        if ($semanaIntensivaFilter) {
            $query->where(function ($q) use ($semanaIntensivaFilter, $periodId) {
                $q->where('semana_intensiva_id', $semanaIntensivaFilter)
                    ->orWhereHas('assignments', function ($subQ) use ($semanaIntensivaFilter, $periodId) {
                        $subQ->where('semana_intensiva_id', $semanaIntensivaFilter)->where('period_id', $periodId)->where('is_active', true);
                    });
            });
        }

        $students = $query->paginate($perPage, ['*'], 'page', $page);

        // Map through results for calculations
        $students->getCollection()->transform(function ($student) {
            $periodCost = $student->period ? $student->period->price : 0;
            $totalPaid = $student->transactions->sum('amount');
            $student->current_debt = $periodCost - $totalPaid;

            // Re-using the already loaded assignment info from our optimized 'with'
            $student->period_assignments = $student->assignments;
            return $student;
        });

        return response()->json($students);
    }

    /**
     * Re-sync a specific assignment to Moodle.
     */
    public function resyncToMoodle($id)
    {
        $assignment = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
            ->findOrFail($id);

        $this->syncAssignmentWithMoodle($assignment);

        return response()->json([
            'message' => 'Reasignación a Moodle enviada correctamente',
            'assignment' => $assignment->fresh(['student', 'period', 'grupo', 'semanaIntensiva', 'carrera'])
        ]);
    }

    /**
     * Sync assignment with Moodle when creating new assignments.
     */
    private function syncAssignmentWithMoodle(StudentAssignment $assignment)
    {
        try {
            $student = $assignment->student;
            if (!$student)
                return;

            $this->ensureStudentHasMoodleId($student);
            $cohortsToAdd = [];

            if ($assignment->grupo_id && $assignment->grupo && $assignment->grupo->moodle_id) {
                $cohortsToAdd[] = [
                    'cohorttype' => ['type' => 'id', 'value' => $assignment->grupo->moodle_id],
                    'usertype' => ['type' => 'username', 'value' => (string) $student->id]
                ];
            }

            if ($assignment->semana_intensiva_id && $assignment->semanaIntensiva && $assignment->semanaIntensiva->moodle_id) {
                $cohortsToAdd[] = [
                    'cohorttype' => ['type' => 'id', 'value' => $assignment->semanaIntensiva->moodle_id],
                    'usertype' => ['type' => 'username', 'value' => (string) $student->id]
                ];
            }

            if ($assignment->carrer_id && $assignment->carrera) {
                $carrera = $assignment->carrera()->with('modulos')->first();
                foreach ($carrera->modulos as $modulo) {
                    if ($modulo->moodle_id) {
                        $cohortsToAdd[] = [
                            'cohorttype' => ['type' => 'id', 'value' => $modulo->moodle_id],
                            'usertype' => ['type' => 'username', 'value' => (string) $student->id]
                        ];
                    }
                }
            }

            if (!empty($cohortsToAdd)) {
                $this->moodleService->cohorts()->addUserToCohort($cohortsToAdd);
            }

        } catch (\Exception $e) {
            Log::error('Moodle sync error: ' . $e->getMessage());
        }
    }

    private function updateMoodleCohorts(StudentAssignment $assignment, $oldG, $newG, $oldS, $newS, $oldC, $newC)
    {
        $student = $assignment->student;
        if (!$student || !$student->moodle_id)
            return;

        $toRemove = $this->prepareCohortsToRemove($student, $oldG, $newG, $oldS, $newS, $oldC, $newC);
        if (!empty($toRemove))
            $this->moodleService->cohorts()->removeUsersFromCohorts($toRemove);

        $toAdd = $this->prepareCohortsToAdd($student, $oldG, $newG, $oldS, $newS, $oldC, $newC);
        if (!empty($toAdd))
            $this->moodleService->cohorts()->addUserToCohort($toAdd);
    }

    private function prepareCohortsToRemove($student, $oldGrupoId, $newGrupoId, $oldS, $newS, $oldC, $newC)
    {
        $cohortsToRemove = [];
        $sid = $student->moodle_id;

        if ($oldGrupoId && $oldGrupoId !== $newGrupoId) {
            $g = Grupo::find($oldGrupoId);
            if ($g && $g->moodle_id)
                $cohortsToRemove[] = ['cohortid' => $g->moodle_id, 'userid' => $sid];
        }

        if ($oldS && $oldS !== $newS) {
            $s = SemanaIntensiva::find($oldS);
            if ($s && $s->moodle_id)
                $cohortsToRemove[] = ['cohortid' => $s->moodle_id, 'userid' => $sid];
        }

        if ($oldC && $oldC !== $newC) {
            $c = Carrera::with('modulos')->find($oldC);
            if ($c) {
                foreach ($c->modulos as $m) {
                    if ($m->moodle_id)
                        $cohortsToRemove[] = ['cohortid' => $m->moodle_id, 'userid' => $sid];
                }
            }
        }
        return $cohortsToRemove;
    }

    private function prepareCohortsToAdd($student, $oldG, $newGrupoId, $oldS, $newS, $oldC, $newC)
    {
        $cohortsToAdd = [];
        $uname = (string) $student->id;

        if ($newGrupoId && $newGrupoId !== $oldG) {
            $g = Grupo::find($newGrupoId);
            if ($g && $g->moodle_id)
                $cohortsToAdd[] = ['cohorttype' => ['type' => 'id', 'value' => $g->moodle_id], 'usertype' => ['type' => 'username', 'value' => $uname]];
        }

        if ($newS && $newS !== $oldS) {
            $s = SemanaIntensiva::find($newS);
            if ($s && $s->moodle_id)
                $cohortsToAdd[] = ['cohorttype' => ['type' => 'id', 'value' => $s->moodle_id], 'usertype' => ['type' => 'username', 'value' => $uname]];
        }

        if ($newC && $newC !== $oldC) {
            $c = Carrera::with('modulos')->find($newC);
            if ($c) {
                foreach ($c->modulos as $m) {
                    if ($m->moodle_id)
                        $cohortsToAdd[] = ['cohorttype' => ['type' => 'id', 'value' => $m->moodle_id], 'usertype' => ['type' => 'username', 'value' => $uname]];
                }
            }
        }
        return $cohortsToAdd;
    }

    private function removeAssignmentFromMoodle(StudentAssignment $assignment)
    {
        try {
            $student = $assignment->student;
            if (!$student || !$student->moodle_id)
                return;

            $cohortsToRemove = [];
            if ($assignment->grupo_id && $assignment->grupo && $assignment->grupo->moodle_id) {
                $cohortsToRemove[] = ['cohortid' => $assignment->grupo->moodle_id, 'userid' => $student->moodle_id];
            }

            if ($assignment->semana_intensiva_id && $assignment->semanaIntensiva && $assignment->semanaIntensiva->moodle_id) {
                $cohortsToRemove[] = ['cohortid' => $assignment->semanaIntensiva->moodle_id, 'userid' => $student->moodle_id];
            }

            if ($assignment->carrer_id && $assignment->carrera) {
                $carrera = $assignment->carrera()->with('modulos')->first();
                foreach ($carrera->modulos as $modulo) {
                    if ($modulo->moodle_id)
                        $cohortsToRemove[] = ['cohortid' => $modulo->moodle_id, 'userid' => $student->moodle_id];
                }
            }

            if (!empty($cohortsToRemove))
                $this->moodleService->cohorts()->removeUsersFromCohorts($cohortsToRemove);

        } catch (\Exception $e) {
            Log::error('Moodle removal error: ' . $e->getMessage());
        }
    }

    private function ensureStudentHasMoodleId(Student $student): void
    {
        if (!$student->moodle_id) {
            $username = (string) $student->id;
            $moodleUser = $this->moodleService->users()->getUserByUsername($username);

            if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                $student->moodle_id = $moodleUser['data']['id'];
                $student->save();
            } else {
                throw new \Exception('Failed to fetch Moodle ID for student: ' . $student->id);
            }
        }
    }

    public function getStudentGrades(Request $request, $studentId)
    {
        try {
            $student = Student::find($studentId);
            if (!$student)
                return response()->json(['message' => 'Alumno no encontrado'], 404);

            // Verificamos si el service existe antes de llamarlo
            if (!method_exists($this->gradesService, 'getStudentGrades')) {
                throw new \Exception("El método getStudentGrades no existe en StudentGradesService.");
            }

            $result = $this->gradesService->getStudentGrades($student);

            if (isset($result['success']) && !$result['success']) {
                // Return successful response with empty grades instead of 500 error
                return response()->json([
                    'student' => [
                        'id' => $student->id,
                        'matricula' => $student->id,
                        'firstname' => $student->firstname,
                        'lastname' => $student->lastname,
                        'moodle_id' => $student->moodle_id,
                    ],
                    'courses_count' => 0,
                    'grades' => []
                ]);
            }

            return response()->json($result['data'] ?? $result);
        } catch (\Exception $e) {
            Log::error("Error en notas alumno {$studentId}: " . $e->getMessage());
            return response()->json([
                'message' => 'Error interno al obtener calificaciones',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentCourses(Request $request, $studentId)
    {
        try {
            $student = Student::findOrFail($studentId);
            $this->ensureStudentHasMoodleId($student);
            $gradesOverview = $this->moodleService->grades()->getCourseGradesOverview($student->moodle_id);

            if (!$gradesOverview || !isset($gradesOverview['grades'])) {
                return response()->json(['message' => 'No courses found', 'student' => $student, 'courses' => []]);
            }

            $courses = collect($gradesOverview['grades'])->map(fn($c) => [
                'moodle_course_id' => $c['courseid'],
                'course_name' => $c['coursename'] ?? 'Desconocido',
                'course_shortname' => $c['courseshortname'] ?? '',
                'grade' => $c['grade'] ?? null,
            ]);

            return response()->json(['student' => $student, 'courses' => $courses]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener cursos', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCourseActivities(Request $request, $studentId, $courseId)
    {
        try {
            $student = Student::findOrFail($studentId);
            $this->ensureStudentHasMoodleId($student);
            $courseContents = $this->moodleService->courses()->getCourseContents($courseId);

            if (!$courseContents)
                return response()->json(['message' => 'No contents', 'data' => []], 404);

            $activities = [];
            foreach ($courseContents as $section) {
                foreach ($section['modules'] ?? [] as $module) {
                    if (in_array($module['modname'] ?? '', ['assign', 'quiz', 'forum', 'workshop'])) {
                        $activities[] = [
                            'id' => $module['id'],
                            'name' => $module['name'],
                            'type' => $module['modname'],
                            'section' => $section['name'],
                        ];
                    }
                }
            }

            return response()->json(['student_id' => $student->id, 'course_id' => $courseId, 'activities' => $activities]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }
}