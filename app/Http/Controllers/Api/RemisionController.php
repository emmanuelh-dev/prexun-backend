<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Remision;
use Illuminate\Http\Request;

class RemisionController extends Controller
{
    public function index()
    {
        $remisions = Remision::all();
        return response()->json($remisions);
    }

    public function store(Request $request)
    {
        $remision = Remision::create($request->all());
        return response()->json($remision, 201);
    }

    public function show($id)
    {
        $remision = Remision::find($id);
        return response()->json($remision);
    }
}

