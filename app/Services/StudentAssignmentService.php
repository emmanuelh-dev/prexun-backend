<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\Modulo;
use App\Models\StudentAssignment;
use App\Services\Moodle\MoodleService;
use Illuminate\Support\Facades\Log;

class StudentAssignmentService
{
    protected MoodleService $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * Rebalances Moodle cohort memberships for all active assignments
     * linked to a carrera when its módulos change.
     *
     * Only módulos that were actually added or removed are synced.
     * The student's grupo and semana_intensiva cohorts are left untouched.
     *
     * @param  Carrera  $carrera
     * @param  array    $oldModuloIds  IDs of módulos before the change
     * @param  array    $newModuloIds  IDs of módulos after the change
     * @return array    ['success' => [student_id, ...], 'errors' => [[student_id, error], ...], 'skipped' => int]
     */
    public function rebalanceModulesForCarrera(Carrera $carrera, array $oldModuloIds, array $newModuloIds): array
    {
        $oldModuloIds = array_map('intval', $oldModuloIds);
        $newModuloIds = array_map('intval', $newModuloIds);

        // Determine which módulos were actually added / removed
        $removedModuloIds = array_values(array_diff($oldModuloIds, $newModuloIds));
        $addedModuloIds   = array_values(array_diff($newModuloIds, $oldModuloIds));

        // Nothing changed in terms of módulos — nothing to do
        if (empty($removedModuloIds) && empty($addedModuloIds)) {
            return ['success' => [], 'errors' => [], 'skipped' => 0];
        }

        Log::info('StudentAssignmentService: rebalancing modules for carrera', [
            'carrera_id'        => $carrera->id,
            'carrera_name'      => $carrera->name,
            'removed_modulo_ids' => $removedModuloIds,
            'added_modulo_ids'   => $addedModuloIds,
        ]);

        // Load Moodle IDs for the módulos we need to act on
        $removedMoodleIds = $this->getMoodleIdsForModulos($removedModuloIds);
        $addedMoodleIds   = $this->getMoodleIdsForModulos($addedModuloIds);

        // Fetch all active assignments for this carrera with student moodle_id
        $assignments = StudentAssignment::with('student')
            ->where('carrer_id', $carrera->id)
            ->where('is_active', true)
            ->get();

        if ($assignments->isEmpty()) {
            Log::info('StudentAssignmentService: no active assignments found for carrera', [
                'carrera_id' => $carrera->id,
            ]);
            return ['success' => [], 'errors' => [], 'skipped' => 0];
        }

        $results  = ['success' => [], 'errors' => [], 'skipped' => 0];
        $batchRemove = [];
        $batchAdd    = [];

        foreach ($assignments as $assignment) {
            $student = $assignment->student;

            if (!$student) {
                $results['skipped']++;
                continue;
            }

            // Ensure the student has a Moodle ID
            if (!$student->moodle_id) {
                try {
                    $moodleUser = $this->moodleService->users()->getUserByUsername((string) $student->id);
                    if (($moodleUser['status'] ?? '') === 'success' && isset($moodleUser['data']['id'])) {
                        $student->moodle_id = $moodleUser['data']['id'];
                        $student->save();
                    } else {
                        Log::warning('StudentAssignmentService: could not find Moodle ID for student', [
                            'student_id' => $student->id,
                        ]);
                        $results['errors'][] = [
                            'student_id' => $student->id,
                            'error'      => 'No se encontró ID de Moodle para el alumno',
                        ];
                        continue;
                    }
                } catch (\Throwable $e) {
                    Log::error('StudentAssignmentService: exception fetching Moodle ID', [
                        'student_id' => $student->id,
                        'error'      => $e->getMessage(),
                    ]);
                    $results['errors'][] = [
                        'student_id' => $student->id,
                        'error'      => $e->getMessage(),
                    ];
                    continue;
                }
            }

            // Build batch entries for this student
            foreach ($removedMoodleIds as $cohortId) {
                $batchRemove[] = ['cohortid' => $cohortId, 'userid' => $student->moodle_id];
            }

            foreach ($addedMoodleIds as $cohortId) {
                $batchAdd[] = [
                    'cohorttype' => ['type' => 'id', 'value' => $cohortId],
                    'usertype'   => ['type' => 'username', 'value' => (string) $student->id],
                ];
            }

            $results['success'][] = $student->id;
        }

        // Execute Moodle batch calls
        if (!empty($batchRemove)) {
            try {
                $removeResult = $this->moodleService->cohorts()->removeUsersFromCohorts($batchRemove);
                if (($removeResult['status'] ?? '') !== 'success') {
                    Log::error('StudentAssignmentService: batch remove from Moodle cohorts failed', [
                        'carrera_id' => $carrera->id,
                        'error'      => $removeResult['message'] ?? 'Unknown error',
                    ]);
                } else {
                    Log::info('StudentAssignmentService: batch remove from Moodle cohorts succeeded', [
                        'carrera_id'    => $carrera->id,
                        'entries_count' => count($batchRemove),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('StudentAssignmentService: exception on batch remove from Moodle', [
                    'carrera_id' => $carrera->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if (!empty($batchAdd)) {
            try {
                $addResult = $this->moodleService->cohorts()->addUserToCohort($batchAdd);
                if (($addResult['status'] ?? '') !== 'success') {
                    Log::error('StudentAssignmentService: batch add to Moodle cohorts failed', [
                        'carrera_id' => $carrera->id,
                        'error'      => $addResult['message'] ?? 'Unknown error',
                    ]);
                } else {
                    Log::info('StudentAssignmentService: batch add to Moodle cohorts succeeded', [
                        'carrera_id'    => $carrera->id,
                        'entries_count' => count($batchAdd),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('StudentAssignmentService: exception on batch add to Moodle', [
                    'carrera_id' => $carrera->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        Log::info('StudentAssignmentService: rebalance complete', [
            'carrera_id'      => $carrera->id,
            'students_synced' => count($results['success']),
            'students_errors' => count($results['errors']),
            'students_skipped' => $results['skipped'],
        ]);

        return $results;
    }

    /**
     * Returns the Moodle cohort IDs for the given local módulo IDs.
     * Only returns IDs of módulos that actually have a moodle_id set.
     */
    private function getMoodleIdsForModulos(array $moduloIds): array
    {
        if (empty($moduloIds)) {
            return [];
        }

        return Modulo::whereIn('id', $moduloIds)
            ->whereNotNull('moodle_id')
            ->pluck('moodle_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
