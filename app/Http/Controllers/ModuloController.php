<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;
use App\Services\Moodle;
use Illuminate\Support\Facades\Log;

class ModuloController extends Controller
{
    protected $moodleService;

    public function __construct(Moodle $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    public function index()
    {
        $modulos = Modulo::all();
        return response()->json($modulos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $modulo = Modulo::create($validated);

        try {
            $cohortName = $modulo->name;
            
            $cohortData = [
                'cohorts' => [[
                    'name' => $cohortName,
                    'idnumber' => 'M' . $modulo->id,
                    'description' => 'Cohorte para el módulo ' . $modulo->name,
                    'descriptionformat' => 1,
                    'visible' => 1,
                    'categorytype' => [
                        'type' => 'system',
                        'value' => ''
                    ]
                ]]
            ];
            
            $response = $this->moodleService->createCohorts($cohortData);
            
            if ($response['status'] === 'success' && isset($response['data'][0]['id'])) {
                $modulo->moodle_id = $response['data'][0]['id'];
                $modulo->save();
                
                Log::info('Cohort created in Moodle for Modulo', [
                    'modulo_id' => $modulo->id,
                    'moodle_id' => $modulo->moodle_id,
                    'cohort_name' => $cohortName
                ]);
            } else {
                Log::error('Failed to create cohort in Moodle for Modulo', [
                    'modulo_id' => $modulo->id,
                    'error' => $response['message'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception creating cohort in Moodle for Modulo', [
                'modulo_id' => $modulo->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json($modulo, 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        $modulo = Modulo::findOrFail($id);
        $oldName = $modulo->name;
        
        $modulo->update($validated);
        
        if ($oldName !== $modulo->name && $modulo->moodle_id) {
            try {
                $cohortName = $modulo->name;
                
                $cohortData = [
                    'cohorts' => [[
                        'id' => $modulo->moodle_id,
                        'name' => $cohortName,
                        'idnumber' => 'M' . $modulo->id,
                        'description' => 'Cohorte para el módulo ' . $modulo->name,
                        'descriptionformat' => 1,
                        'visible' => 1
                    ]]
                ];
                
                $response = $this->moodleService->updateCohorts($cohortData);
                
                if ($response['status'] === 'success') {
                    Log::info('Cohort updated in Moodle for Modulo', [
                        'modulo_id' => $modulo->id,
                        'moodle_id' => $modulo->moodle_id,
                        'cohort_name' => $cohortName
                    ]);
                } else {
                    Log::error('Failed to update cohort in Moodle for Modulo', [
                        'modulo_id' => $modulo->id,
                        'error' => $response['message'] ?? 'Unknown error'
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception updating cohort in Moodle for Modulo', [
                    'modulo_id' => $modulo->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return response()->json($modulo);
    }

    public function destroy($id)
    {
        $modulo = Modulo::find($id);

        // Opcional: Eliminar el cohort de Moodle si existe
        // if ($modulo->moodle_id) {
        //     try {
        //         $this->moodleService->deleteCohorts(['cohortids' => [$modulo->moodle_id]]);
        //         Log::info('Cohort deleted in Moodle for Modulo', ['modulo_id' => $id, 'moodle_id' => $modulo->moodle_id]);
        //     } catch (\Exception $e) {
        //         Log::error('Exception deleting cohort in Moodle for Modulo', ['modulo_id' => $id, 'moodle_id' => $modulo->moodle_id, 'error' => $e->getMessage()]);
        //     }
        // }

        $modulo->delete();
        return response()->json(['message' => 'Modulo deleted successfully']);
    }
}
