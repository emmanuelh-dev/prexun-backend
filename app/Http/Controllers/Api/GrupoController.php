<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grupo;

class GrupoController extends Controller
{
    public function index()
    {
        $grupos = Grupo::all();
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
            'end_time' => 'required|date_format:H:i'
        ]);

        $validated['frequency'] = json_encode($validated['frequency']);
        
        $grupo = Grupo::create($validated);
        return response()->json($grupo, 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'string',
            'type' => 'string',
            'plantel_id' => 'integer|min:1',
            'period_id' => 'integer|min:1', 
            'capacity' => 'integer|min:1',
            'frequency' => 'array',
            'start_time' => 'date_format:H:i',
            'end_time' => 'date_format:H:i'
        ]);

        if (isset($validated['frequency'])) {
            $validated['frequency'] = json_encode($validated['frequency']);
        }

        $grupo = Grupo::findOrFail($id);
        $grupo->update($validated);
        return response()->json($grupo);
    }
}
