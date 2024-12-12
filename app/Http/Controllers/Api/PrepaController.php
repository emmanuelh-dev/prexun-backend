<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prepa;
use Illuminate\Http\Request;

class PrepaController extends Controller
{
    public function index()
    {
        $prepas = Prepa::all();
        return response()->json($prepas);
    }

    public function store(Request $request)
    {
        $prepas = Prepa::create($request->all());
        return response()->json($prepas, 201);
    }

    public function update(Request $request, $id)
    {
        $prepas = Prepa::findOrFail($id);
        $prepas->update($request->all());
        return response()->json($prepas);
    }

    public function destroy($id)
    {
        $prepas = Prepa::findOrFail($id);
        $prepas->delete();
        return response()->json(['message' => 'Prepa deleted successfully']);
    }   
}
