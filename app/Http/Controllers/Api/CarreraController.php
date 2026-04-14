<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\CarreraModulo;
use App\Models\Modulo;
use App\Services\StudentAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CarreraController extends Controller
{
  public function index()
  {
      $carreras = Carrera::with('modulos')
          ->orderByRaw('orden IS NULL')
          ->orderBy('orden', 'asc')
          ->get();
      return response()->json($carreras);
  }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'facultad_id' => 'required|exists:facultades,id',
            'orden' => 'nullable|integer|unique:carreers,orden',
            'modulo_ids' => 'sometimes|array',
            'modulo_ids.*' => 'exists:modulos,id',
        ]);

        $carrera = Carrera::create([
            'name' => $validatedData['name'],
            'facultad_id' => $validatedData['facultad_id'],
            'orden' => $validatedData['orden'] ?? null,
        ]);

        if (isset($validatedData['modulo_ids'])) {
            $carrera->modulos()->sync($validatedData['modulo_ids']);
        }

        return response()->json($carrera->load('modulos'), 201);
    }

    public function update(Request $request, $id)
    {
        $carrera = Carrera::with('modulos')->find($id);

        if (!$carrera) {
            return response()->json(['message' => 'Carrera no encontrada'], 404);
        }

        $validatedData = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'facultad_id'   => 'sometimes|required|exists:facultades,id',
            'orden'         => 'nullable|integer|unique:carreers,orden,' . $id,
            'modulo_ids'    => 'sometimes|array',
            'modulo_ids.*'  => 'exists:modulos,id',
        ]);

        $carrera->update([
            'name'        => $validatedData['name']        ?? $carrera->name,
            'facultad_id' => $validatedData['facultad_id'] ?? $carrera->facultad_id,
            'orden'       => $validatedData['orden']       ?? $carrera->orden,
        ]);

        // ── Module rebalancing ──────────────────────────────────────────────────
        $moodleResync = null;

        if ($request->has('modulo_ids')) {
            // Capture current módulo IDs before the sync
            $oldModuloIds = $carrera->modulos->pluck('id')->map(fn ($v) => (int) $v)->all();
            $newModuloIds = isset($validatedData['modulo_ids'])
                ? array_map('intval', $validatedData['modulo_ids'])
                : [];

            // Sync the pivot table
            $carrera->modulos()->sync($newModuloIds);

            // Rebalance Moodle cohorts for all affected active assignments
            if ($oldModuloIds !== $newModuloIds) {
                try {
                    /** @var StudentAssignmentService $service */
                    $service      = app(StudentAssignmentService::class);
                    $moodleResync = $service->rebalanceModulesForCarrera($carrera, $oldModuloIds, $newModuloIds);

                    Log::info('CarreraController: module rebalance triggered', [
                        'carrera_id'      => $carrera->id,
                        'students_synced' => count($moodleResync['success'] ?? []),
                        'students_errors' => count($moodleResync['errors']  ?? []),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('CarreraController: error during module rebalance', [
                        'carrera_id' => $carrera->id,
                        'error'      => $e->getMessage(),
                    ]);
                    $moodleResync = ['error' => $e->getMessage()];
                }
            }
        }
        // ── End rebalancing ────────────────────────────────────────────────────

        $response = ['carrera' => $carrera->load('modulos')];

        if ($moodleResync !== null) {
            $response['moodle_resync'] = $moodleResync;
        }

        return response()->json($response);
    }

    public function destroy($id)
    {
        $carrera = Carrera::find($id);

        if ($carrera) {
            $carrera->delete();
            return response()->json(['message' => 'Carrera eliminada correctamente']);
        } else {
            return response()->json(['message' => 'Carrera no encontrada'], 404);
        }
    }

    public function getModulos(Carrera $carrera)
    {
        return response()->json($carrera->modulos);
    }

    public function associateModulos(Request $request, Carrera $carrera)
    {
        $validatedData = $request->validate([
            'modulo_ids' => 'required|array',
            'modulo_ids.*' => 'exists:modulos,id',
        ]);

        $carrera->modulos()->sync($validatedData['modulo_ids']);
        return response()->json($carrera->load('modulos'));
    }

    public function dissociateModulo(Carrera $carrera, Modulo $modulo)
    {
        $carrera->modulos()->detach($modulo->id);
        return response()->json(['message' => 'Módulo desasociado correctamente']);
    }
}
