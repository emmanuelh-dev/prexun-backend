<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use Illuminate\Http\Request;

class MunicipioController extends Controller
{
    public function index()
    {
        $municipios = Municipio::all();
        return response()->json($municipios);
    }

    public function store(Request $request)
    {
        $municipios = Municipio::create($request->all());
        return response()->json($municipios, 201);
    }

    public function update(Request $request, $id)
    {
        $municipios = Municipio::findOrFail($id);
        $municipios->update($request->all());
        return response()->json($municipios);
    }

    public function destroy($id)
    {
        $municipios = Municipio::findOrFail($id);
        $municipios->delete();
        return response()->json(['message' => 'Municipio deleted successfully']);
    }
}   