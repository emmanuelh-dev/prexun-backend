<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CampusController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $campuses = Campus::with(['users:id,name,email,role'])->get();
        } else {
            $campuses = $user->campuses()->with(['users:id,name,email,role'])->get();
        }

        return response()->json($campuses);
    }

    public function addAdmin(Request $request)
    {
        $user = $request->user();
        $campus = Campus::findOrFail($request->input('campus_id'));
        $campus->users()->attach($user->id);
        return response()->json(['message' => 'Administrador agregado correctamente']);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:campuses,code',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'is_active' => 'boolean|nullable',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $campusData = [
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'address' => $request->address,
            'is_active' => $request->is_active ?? true,
        ];

        $campus = Campus::create($campusData);

        if ($request->has('admin_ids') && !empty($request->admin_ids)) {
            $campus->users()->attach($request->admin_ids);
        }

        $campus->load('users:id,name,email,role');

        return response()->json($campus, 201);
    }

    public function update(Request $request, $id)
    {
        $campus = Campus::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => [
                'sometimes', 
                'string', 
                Rule::unique('campuses', 'code')->ignore($campus->id)
            ],
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $campus->update($validator->validated());

        // Sync administrators if provided
        if ($request->has('admin_ids')) {
            $campus->users()->sync($request->input('admin_ids'));
        }

        // Cargar las relaciones antes de devolver
        $campus->load('users:id,name,email,role');

        return response()->json($campus);
    }

    public function destroy($id)
    {
        $campus = Campus::findOrFail($id);
        $campus->delete();

        return response()->json(null, 204);
    }

    public function show($id)
    {
        $campus = Campus::with('users:id,name,email,role')->findOrFail($id);
        return response()->json($campus);
    }
}