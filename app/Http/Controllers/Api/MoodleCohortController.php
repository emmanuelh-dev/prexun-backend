<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Moodle\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MoodleCohortController extends Controller
{
    protected MoodleService $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * Eliminar un usuario específico de un cohort específico.
     */
    public function removeUserFromCohort(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'cohort_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->moodleService->cohorts()->removeUserFromCohort(
            $request->user_id,
            $request->cohort_id
        );

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Eliminar múltiples usuarios de múltiples cohorts.
     * Esta es la nueva funcionalidad solicitada.
     */
    public function removeUsersFromCohorts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'members' => 'required|array|min:1',
            'members.*.userid' => 'required|integer',
            'members.*.cohortid' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->moodleService->cohorts()->removeUsersFromCohorts($request->members);

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Eliminar un usuario de todos sus cohorts.
     */
    public function removeUserFromAllCohorts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Username es requerido',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->moodleService->cohorts()->removeUserFromAllCohorts($request->username);

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Agregar usuarios a cohorts.
     */
    public function addUsersToCohorts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'members' => 'required|array|min:1',
            'members.*.userid' => 'required|integer',
            'members.*.cohortid' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $result = $this->moodleService->cohorts()->addUserToCohort($request->members);

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Obtener los cohorts de un usuario.
     */
    public function getUserCohorts(Request $request, $userId): JsonResponse
    {
        if (!is_numeric($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID debe ser un número'
            ], 400);
        }

        $result = $this->moodleService->cohorts()->getUserCohorts((int)$userId);

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }
}
