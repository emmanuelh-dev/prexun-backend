<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grupo;

class GrupoController extends Controller
{
    public function index()
    {
        $grupos = Grupo::with('period')
            ->withCount('students')
            ->get()
            ->map(function ($grupo) {
                $grupo->available_slots = $grupo->capacity - $grupo->students_count;
                $grupo->is_almost_full = $grupo->available_slots <= 3;
                $grupo->is_full = $grupo->available_slots <= 0;
                return $grupo;
            });
        
        return response()->json($grupos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'plantel_id' => 'required|integer|min:1',
            'period_id' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'frequency' => 'required|array',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d'
        ]);

        $validated['frequency'] = json_encode($validated['frequency']);
        
        $grupo = Grupo::create($validated);
        return response()->json($grupo, 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'string|sometimes',
            'type' => 'string|sometimes',
            'plantel_id' => 'integer|min:1|sometimes',
            'period_id' => 'integer|min:1|sometimes', 
            'capacity' => 'integer|min:1|sometimes',
            'frequency' => 'array|nullable',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
                        'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d'
        ]);
    
        $dataToUpdate = [];
    
        foreach(['name', 'type', 'plantel_id', 'period_id', 'capacity', 'start_time', 'end_time', 'start_date', 'end_date'] as $field) {
            if (isset($validated[$field])) {
                $dataToUpdate[$field] = $validated[$field];
            }
        }
    
        if (isset($validated['frequency']) && !empty($validated['frequency'])) {
            $dataToUpdate['frequency'] = json_encode($validated['frequency']);
        }
    
        $grupo = Grupo::findOrFail($id);
        $grupo->update($dataToUpdate);
        
        return response()->json($grupo);
    }
}
