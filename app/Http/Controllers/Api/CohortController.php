<?php

namespace App\Http\Controllers\Api;

use App\Models\Cohort;
use App\Models\Period;
use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Services\Moodle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // Para validación

class CohortController extends Controller
{

    protected $moodleService;

    public function __construct(Moodle $moodleService)
    {
        $this->moodleService = $moodleService;
    }
    /**
     * Display a listing of the cohorts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $cohorts = Cohort::with(['period', 'group'])->get();
        return response()->json($cohorts);
    }

    /**
     * Store a newly created cohort in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period_id' => 'required|exists:periods,id',
            'grupo_id' => 'required|exists:grupos,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $cohort = new Cohort();
        $cohort->period_id = $request->period_id;
        $cohort->grupo_id = $request->grupo_id;
        $cohort->save(); // El nombre se genera automáticamente en el modelo

        return response()->json($cohort, 201); // 201 Created
    }


    /**
     * Generate cohorts automatically.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate()
    {
        $periods = Period::all();
        $grupos = Grupo::all();
        $createdCohorts = [];
        $failedCohorts = [];

        foreach ($periods as $period) {
            foreach ($grupos as $grupo) {
                try {
                    $existingCohort = Cohort::where('period_id', $period->id)
                        ->where('grupo_id', $grupo->id)
                        ->first();

                    if (!$existingCohort) {
                        $cohort = new Cohort();
                        $cohort->period_id = $period->id;
                        $cohort->grupo_id = $grupo->id;

                        $errorMessage = null; // Variable para almacenar el mensaje de error específico

                        if ($period && $grupo) { // Verifica que ambos existan
                            if ($period->name && $grupo->name) {
                                $cohort->name = $period->name . $grupo->name;
                            } else {
                                $errorMessage = "Period or Group name is null";
                            }
                        } else {
                            $errorMessage = "Period or Group is null";
                        }

                        if ($errorMessage) {
                            $failedCohorts[] = [
                                'period_id' => $period->id,
                                'grupo_id' => $grupo->id,
                                'error' => $errorMessage, // Mensaje de error específico
                            ];
                            continue; // Saltar a la siguiente iteración
                        }

                        $cohort->save();
                        $createdCohorts[] = $cohort;
                    }
                } catch (\Exception $e) {
                    $failedCohorts[] = [
                        'period_id' => $period->id,
                        'grupo_id' => $grupo->id,
                        'error' => "Relation not found: " . $e->getMessage() .
                            " in model: " . get_class($cohort) . // Modelo Cohort
                            " for period: " . ($period ? get_class($period) : 'null') . // Modelo Period o null
                            " and group: " . ($grupo ? get_class($grupo) : 'null'), // Modelo Grupo o null
                    ];
                    continue;
                    continue;
                }
            }
        }

        return response()->json([
            'message' => 'Cohorts generated (see details for failures)',
            'created' => $createdCohorts,
            'failed' => $failedCohorts,
        ], 200);
    }

    public function syncWithMoodle(Request $request)
    {
        try {
            $cohorts = Cohort::with(['period', 'group'])->get();
    
            if ($cohorts->isEmpty()) {
                Log::info('No cohorts found in database');
                return response()->json(['message' => 'No cohorts found.'], 404);
            }
    
            $moodleCohorts = [
                'cohorts' => []
            ];
            
            foreach ($cohorts as $cohort) {
                // Validar que period existe
                if (!$cohort->period) {
                    Log::warning("Cohort ID {$cohort->id} has no associated period");
                    continue;
                }
    
                $cohortData = [
                    'categorytype' => [
                        'type' => 'string',
                        'value' => (string)($cohort->period->id ?? '')
                    ],
                    'name' => $cohort->name ?? '',
                    'idnumber' => (string)$cohort->id,
                    'description' => 'Descripción del cohorte ' . ($cohort->name ?? ''),
                    'descriptionformat' => 1,
                    'visible' => 1,
                    'theme' => '',
                    'customfields' => []
                ];
    
                // Solo añadir custom fields si group_id existe
                if ($cohort->group_id) {
                    $cohortData['customfields'][] = [
                        'shortname' => 'group_id',
                        'value' => (string)$cohort->group_id
                    ];
                }
    
                $moodleCohorts['cohorts'][] = $cohortData;
            }

            
            $syncResult = $this->moodleService->createCohorts($moodleCohorts);
    
            if ($syncResult['status'] === 'success') {
                return response()->json([
                    'message' => 'Cohorts synchronized successfully',
                    'data' => $syncResult['data'],
                    'moodle_cohort_ids' => $syncResult['moodle_cohort_ids'],
                ], 200);
            } else {
                Log::error("Moodle Cohort Sync Error: " . ($syncResult['message'] ?? 'No error message'));
                return response()->json(['error' => $syncResult['message']], $syncResult['code'] ?? 500);
            }
        } catch (\Exception $e) {
            Log::error("Moodle Cohort Sync Error (Unexpected): " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to sync cohorts: ' . $e->getMessage()], 500);
        }
    }
}
