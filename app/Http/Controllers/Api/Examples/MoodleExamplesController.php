<?php

namespace App\Http\Controllers\Api\Examples;

use App\Http\Controllers\Controller;
use App\Services\Moodle\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Ejemplos prácticos de uso de los servicios de Moodle refactorizados
 */
class MoodleExamplesController extends Controller
{
    protected MoodleService $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * Ejemplo 1: Eliminar múltiples estudiantes de un cohort específico
     */
    public function removeStudentsFromCohort(Request $request): JsonResponse
    {
        // Supongamos que tenemos una lista de estudiantes y un cohort
        $studentIds = $request->input('student_ids'); // [123, 124, 125]
        $cohortId = $request->input('cohort_id'); // 456

        // Preparar los datos para la API de Moodle
        $members = [];
        foreach ($studentIds as $studentId) {
            $members[] = [
                'userid' => $studentId,
                'cohortid' => $cohortId
            ];
        }

        // Usar la nueva función para eliminar todos de una vez
        $result = $this->moodleService->cohorts()->removeUsersFromCohorts($members);

        return response()->json([
            'message' => 'Estudiantes eliminados del cohort',
            'students_processed' => count($members),
            'moodle_response' => $result
        ]);
    }

    /**
     * Ejemplo 2: Transferir estudiantes entre cohorts
     */
    public function transferStudentsBetweenCohorts(Request $request): JsonResponse
    {
        $studentIds = $request->input('student_ids');
        $fromCohortId = $request->input('from_cohort_id');
        $toCohortId = $request->input('to_cohort_id');

        // Paso 1: Eliminar estudiantes del cohort origen
        $membersToRemove = [];
        foreach ($studentIds as $studentId) {
            $membersToRemove[] = [
                'userid' => $studentId,
                'cohortid' => $fromCohortId
            ];
        }

        $removeResult = $this->moodleService->cohorts()->removeUsersFromCohorts($membersToRemove);

        if ($removeResult['status'] !== 'success') {
            return response()->json([
                'status' => 'error',
                'message' => 'Error eliminando estudiantes del cohort origen',
                'details' => $removeResult
            ], 400);
        }

        // Paso 2: Agregar estudiantes al cohort destino
        $membersToAdd = [];
        foreach ($studentIds as $studentId) {
            $membersToAdd[] = [
                'userid' => $studentId,
                'cohortid' => $toCohortId
            ];
        }

        $addResult = $this->moodleService->cohorts()->addUserToCohort($membersToAdd);

        return response()->json([
            'status' => 'success',
            'message' => 'Estudiantes transferidos exitosamente',
            'students_transferred' => count($studentIds),
            'remove_result' => $removeResult,
            'add_result' => $addResult
        ]);
    }

    /**
     * Ejemplo 3: Limpiar un usuario de todos los cohorts y agregarlo a uno nuevo
     */
    public function reassignUserCohort(Request $request): JsonResponse
    {
        $username = $request->input('username');
        $newCohortId = $request->input('new_cohort_id');

        // Paso 1: Obtener el usuario
        $userResult = $this->moodleService->users()->getUserByUsername($username);
        
        if ($userResult['status'] !== 'success') {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $userId = $userResult['data']['id'];

        // Paso 2: Eliminar de todos los cohorts
        $removeAllResult = $this->moodleService->cohorts()->removeUserFromAllCohorts($username);        // Paso 3: Agregar al nuevo cohort - usar formato cohorttype/usertype
        $addResult = $this->moodleService->cohorts()->addUserToCohort([
            [
                'cohorttype' => ['type' => 'id', 'value' => $newCohortId],
                'usertype' => ['type' => 'username', 'value' => $username]
            ]
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario reasignado exitosamente',
            'user_id' => $userId,
            'remove_all_result' => $removeAllResult,
            'add_result' => $addResult
        ]);
    }

    /**
     * Ejemplo 4: Gestión masiva de cohorts por carrera
     */
    public function manageCohortsByCareer(Request $request): JsonResponse
    {
        $careerCohortId = $request->input('career_cohort_id');
        $actions = $request->input('actions'); // Array de acciones

        $results = [];

        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'add_students':
                    $members = [];
                    foreach ($action['student_ids'] as $studentId) {
                        $members[] = [
                            'userid' => $studentId,
                            'cohortid' => $careerCohortId
                        ];
                    }
                    $results[] = [
                        'action' => 'add_students',
                        'result' => $this->moodleService->cohorts()->addUserToCohort($members)
                    ];
                    break;

                case 'remove_students':
                    $members = [];
                    foreach ($action['student_ids'] as $studentId) {
                        $members[] = [
                            'userid' => $studentId,
                            'cohortid' => $careerCohortId
                        ];
                    }
                    $results[] = [
                        'action' => 'remove_students',
                        'result' => $this->moodleService->cohorts()->removeUsersFromCohorts($members)
                    ];
                    break;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Acciones masivas completadas',
            'results' => $results
        ]);
    }

    /**
     * Ejemplo 5: Reporte de cohorts de un usuario
     */
    public function getUserCohortReport(Request $request, $username): JsonResponse
    {
        // Obtener usuario
        $userResult = $this->moodleService->users()->getUserByUsername($username);
        
        if ($userResult['status'] !== 'success') {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $userId = $userResult['data']['id'];
        $userData = $userResult['data'];

        // Obtener cohorts
        $cohortsResult = $this->moodleService->cohorts()->getUserCohorts($userId);

        return response()->json([
            'status' => 'success',
            'user_info' => [
                'id' => $userData['id'],
                'username' => $userData['username'],
                'fullname' => $userData['fullname'],
                'email' => $userData['email']
            ],
            'cohorts' => $cohortsResult['data']['cohorts'] ?? [],
            'total_cohorts' => count($cohortsResult['data']['cohorts'] ?? [])
        ]);
    }
}
