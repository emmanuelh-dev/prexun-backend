<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facultad;
use Illuminate\Http\Request;

class FacultadController extends Controller
{
    public function index()
    {
        $facultades = Facultad::all();
        return response()->json($facultades);
    }

    public function store(Request $request)
    {
        $facultad = Facultad::create($request->all());
        return response()->json($facultad, 201);
    }

    public function update(Request $request, $id)
    {
        $facultad = Facultad::find($id);
        $facultad->update($request->all());
        return response()->json($facultad);
    }

    public function destroy($id)
    {
        $facultad = Facultad::find($id);
        $facultad->delete();
        return response()->json(['message' => 'Facultad eliminada correctamente']);
    }
}
