<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Moodle\MoodleService;
use App\Facades\Moodle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Ejemplo práctico de uso de los nuevos servicios de Moodle
 * Este controlador muestra cómo migrar del servicio original a la nueva estructura
 */
class MoodleMigrationExampleController extends Controller
{
    protected MoodleService $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * Ejemplo 1: Migración de estudiantes entre períodos académicos
     * Este es un caso de uso real donde necesitas mover múltiples estudiantes
     * de cohorts de un período anterior a cohorts de un nuevo período
     */
    public function migrateStudentsBetweenPeriods(Request $request): JsonResponse
    {
        $request->validate([
            'student_usernames' => 'required|array',
            'old_period_cohort_names' => 'required|array',
            'new_period_cohort_id' => 'required|integer'
        ]);

        $studentUsernames = $request->student_usernames;
        $oldPeriodCohortNames = $request->old_period_cohort_names;
        $newPeriodCohortId = $request->new_period_cohort_id;

        $results = [];
        $membersToRemove = [];
        $membersToAdd = [];

        try {
            // Paso 1: Obtener IDs de usuarios y sus cohorts actuales
            foreach ($studentUsernames as $username) {
                $userResult = $this->moodleService->users()->getUserByUsername($username);
                
                if ($userResult['status'] !== 'success') {
                    $results[] = [
                        'username' => $username,
                        'status' => 'error',
                        'message' => 'Usuario no encontrado'
                    ];
                    continue;
                }

                $userId = $userResult['data']['id'];
                
                // Obtener cohorts actuales del usuario
                $cohortsResult = $this->moodleService->cohorts()->getUserCohorts($userId);
                
                if ($cohortsResult['status'] === 'success') {
                    // Filtrar cohorts del período anterior
                    foreach ($cohortsResult['data']['cohorts'] as $cohort) {
                        if (in_array($cohort['name'], $oldPeriodCohortNames)) {
                            $membersToRemove[] = [
                                'userid' => $userId,
                                'cohortid' => $cohort['id']
                            ];
                        }
                    }
                }

                // Preparar para agregar al nuevo período
                $membersToAdd[] = [
                    'userid' => $userId,
                    'cohortid' => $newPeriodCohortId
                ];

                $results[] = [
                    'username' => $username,
                    'user_id' => $userId,
                    'status' => 'processed'
                ];
            }

            // Paso 2: Eliminar de cohorts del período anterior (UNA SOLA LLAMADA)
            if (!empty($membersToRemove)) {
                $removeResult = $this->moodleService->cohorts()->removeUsersFromCohorts($membersToRemove);
                Log::info('Bulk remove from old cohorts', [
                    'members_count' => count($membersToRemove),
                    'result' => $removeResult
                ]);
            }

            // Paso 3: Agregar al nuevo período (UNA SOLA LLAMADA)
            if (!empty($membersToAdd)) {
                $addResult = $this->moodleService->cohorts()->addUserToCohort($membersToAdd);
                Log::info('Bulk add to new cohort', [
                    'members_count' => count($membersToAdd),
                    'result' => $addResult
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Migración completada',
                'students_processed' => count($studentUsernames),
                'old_cohorts_removed' => count($membersToRemove),
                'new_cohort_added' => count($membersToAdd),
                'details' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error en migración de estudiantes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error durante la migración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ejemplo 2: Limpieza masiva de cohorts inactivos
     * Usando el Facade para mayor comodidad
     */
    public function cleanupInactiveCohorts(Request $request): JsonResponse
    {
        $request->validate([
            'inactive_cohort_names' => 'required|array'
        ]);

        $inactiveCohortNames = $request->inactive_cohort_names;
        $allMembersToRemove = [];

        try {
            // Obtener IDs de cohorts inactivos
            foreach ($inactiveCohortNames as $cohortName) {
                $cohortId = Moodle::getCohortIdByName($cohortName);
                
                if (!$cohortId) {
                    continue;
                }

                // Aquí normalmente obtendrías todos los usuarios del cohort
                // Para este ejemplo, simularemos algunos usuarios
                $simulatedUserIds = [123, 124, 125]; // En realidad obtendrías esto de la API
                
                foreach ($simulatedUserIds as $userId) {
                    $allMembersToRemove[] = [
                        'userid' => $userId,
                        'cohortid' => $cohortId
                    ];
                }
            }

            // Eliminar todos los usuarios de todos los cohorts inactivos EN UNA SOLA OPERACIÓN
            if (!empty($allMembersToRemove)) {
                $result = Moodle::cohorts()->removeUsersFromCohorts($allMembersToRemove);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Limpieza completada',
                    'cohorts_cleaned' => count($inactiveCohortNames),
                    'total_removals' => count($allMembersToRemove),
                    'moodle_result' => $result
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'No se encontraron usuarios para remover'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error durante la limpieza: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ejemplo 3: Comparación entre método antiguo y nuevo
     * Muestra las ventajas de la nueva funcionalidad
     */
    public function comparisonExample(Request $request): JsonResponse
    {
        $members = [
            ['userid' => 123, 'cohortid' => 456],
            ['userid' => 124, 'cohortid' => 457],
            ['userid' => 125, 'cohortid' => 458]
        ];

        // ❌ MÉTODO ANTIGUO: Múltiples llamadas a la API
        $oldMethodResults = [];
        foreach ($members as $member) {
            $result = $this->moodleService->removeUserFromCohort(
                $member['userid'], 
                $member['cohortid']
            );
            $oldMethodResults[] = $result;
            // Cada iteración = 1 llamada HTTP a Moodle
        }
        // Total: 3 llamadas HTTP

        // ✅ MÉTODO NUEVO: Una sola llamada a la API
        $newMethodResult = $this->moodleService->cohorts()->removeUsersFromCohorts($members);
        // Total: 1 llamada HTTP

        return response()->json([
            'comparison' => [
                'old_method' => [
                    'api_calls' => count($members),
                    'results' => $oldMethodResults
                ],
                'new_method' => [
                    'api_calls' => 1,
                    'result' => $newMethodResult
                ]
            ],
            'performance_improvement' => [
                'api_calls_reduced' => count($members) - 1,
                'efficiency_gain' => ((count($members) - 1) / count($members)) * 100 . '%'
            ]
        ]);
    }

    /**
     * Ejemplo 4: Uso del comando de Artisan desde código
     */
    public function runArtisanCommand(): JsonResponse
    {
        try {
            // Ejecutar comando de Artisan programáticamente
            $exitCode = \Artisan::call('moodle:cohort', [
                'action' => 'remove-users',
                '--members' => json_encode([
                    ['userid' => 123, 'cohortid' => 456],
                    ['userid' => 124, 'cohortid' => 457]
                ])
            ]);

            $output = \Artisan::output();

            return response()->json([
                'status' => $exitCode === 0 ? 'success' : 'error',
                'exit_code' => $exitCode,
                'output' => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error ejecutando comando: ' . $e->getMessage()
            ], 500);
        }
    }
}
