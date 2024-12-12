<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;

class ModuloController extends Controller
{
    public function index()
    {
        $modulos = Modulo::all();
        return response()->json($modulos);
    }

    public function store(Request $request)
    {
        $modulo = Modulo::create($request->all());
        return response()->json($modulo, 201);
    }

    public function update(Request $request, $id)
    {
        $modulo = Modulo::find($id);
        $modulo->update($request->all());
        return response()->json($modulo);
    }

    public function destroy($id)
    {
        $modulo = Modulo::find($id);
        $modulo->delete();
        return response()->json(['message' => 'Modulo deleted successfully']);
    }
}
