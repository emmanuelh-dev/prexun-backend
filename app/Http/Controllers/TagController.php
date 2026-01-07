<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $campusId = $request->query('campus_id');
        
        $query = Tag::with('campus:id,name');
        
        if ($campusId) {
            $query->where('campus_id', $campusId);
        }
        
        $tags = $query->get();
        
        return response()->json($tags);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'campus_id' => 'required|exists:campuses,id',
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $tag = Tag::create($request->only(['campus_id', 'name', 'color']));
        $tag->load('campus:id,name');

        return response()->json($tag, 201);
    }

    public function show(string $id)
    {
        $tag = Tag::with(['campus:id,name', 'students:id,firstname,lastname'])->findOrFail($id);
        return response()->json($tag);
    }

    public function update(Request $request, string $id)
    {
        $tag = Tag::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $tag->update([
            'name' => $request->name,
            'color' => $request->color ?? $tag->color
        ]);
        $tag->load('campus:id,name');

        return response()->json($tag);
    }

    public function destroy(string $id)
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();

        return response()->json(['message' => 'Etiqueta eliminada exitosamente']);
    }
}
