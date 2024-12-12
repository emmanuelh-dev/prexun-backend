<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use Illuminate\Http\Request;

class CarreraController extends Controller
{
    public function index()
    {
        $carreras = Carrera::all();
        return response()->json($carreras);
    }

    public function store(Request $request)
    {
        $carrera = Carrera::create($request->all());
        return response()->json($carrera, 201);
    }

    public function update(Request $request, $id)
    {
        $carrera = Carrera::findOrFail($id);
        $carrera->update($request->all());
        return response()->json($carrera);
    }

    public function destroy($id)
    {
        $carrera = Carrera::findOrFail($id);
        $carrera->delete();
        return response()->json(['message' => 'Carrera eliminada correctamente']);
    }
}
