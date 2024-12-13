<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\CarreraModulo;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CarreraController extends Controller
{
    public function index()
    {
        $carreras = Carrera::with('modulos')->get();
        return response()->json($carreras);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'facultad_id' => 'required|exists:facultades,id',
            'modulo_ids' => 'sometimes|array',
            'modulo_ids.*' => 'exists:modulos,id',
        ]);
    
        $carrera = Carrera::create([
            'name' => $validatedData['name'],
            'facultad_id' => $validatedData['facultad_id'],
        ]);
    
        if (isset($validatedData['modulo_ids'])) {
            $carrera->modulos()->sync($validatedData['modulo_ids']);
        }
    
        return response()->json($carrera->load('modulos'), 201);
    }

    public function update(Request $request, $id)
    {
        $carrera = Carrera::find($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'facultad_id' => 'sometimes|required|exists:facultades,id',
            'modulos' => 'sometimes|array',
            'modulos.*.id' => 'exists:modulos,id',
        ]);
    
        $carrera->update([
            'name' => $validatedData['name'] ?? $carrera->name,
            'facultad_id' => $validatedData['facultad_id'] ?? $carrera->facultad_id,
        ]);
    
        if (isset($validatedData['modulos'])) {
            $moduloIds = collect($validatedData['modulos'])->pluck('id')->toArray();
            $carrera->modulos()->sync($moduloIds);
        } elseif ($request->has('modulos')) {
            $carrera->modulos()->sync([]);
        }
    
        return response()->json($carrera->load('modulos'));
    }
    public function destroy(Carrera $carrera)
    {
        $carrera->delete();
        return response()->json(['message' => 'Carrera eliminada correctamente']);
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