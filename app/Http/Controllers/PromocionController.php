<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use Illuminate\Http\Request;

class PromocionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $promociones = Promocion::all();
        $promocionesActivas = Promocion::where('active', true)->get();
        $promocionesInactivas = Promocion::where('active', false)->get();

        return response()->json(
            [
                'promociones' => $promociones,
                'active' => $promocionesActivas,
                'inactive' => $promocionesInactivas
            ]
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'regular_cost' => 'numeric',
            'cost' => 'required|numeric',
            'limit_date' => 'required|date',
            'groups' => 'array',
            'pagos' => 'array',
            'active' => 'required|boolean'
        ]);

        $promocion = Promocion::create($request->all());

        return response()->json($promocion, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Promocion $promocion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'string',
            'type' => 'string',
            'regular_cost' => 'numeric',
            'cost' => 'numeric',
            'limit_date' => 'date',
            'groups' => 'array',
            'pagos' => 'array',
            'active' => 'boolean'
        ]);

        $promocion = Promocion::findOrFail($id);

        $promocion->update($request->all());

        return response()->json($promocion);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $promocion = Promocion::findOrFail($id);
        $promocion->delete();

        return response()->json(['message' => 'Promocion eliminada correctamente'], 204);
    }
}
