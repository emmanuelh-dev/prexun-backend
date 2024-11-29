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
            // Super admin can see all campuses
            $campuses = Campus::all();
        } else {
            // Regular admin sees only their assigned campuses
            $campuses = $user->campuses;
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
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $campus = Campus::create($validator->validated());

        // Attach administrators if provided
        if ($request->has('admin_ids')) {
            $campus->users()->attach($request->input('admin_ids'));
        }

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
        $campus = Campus::with('users')->findOrFail($id);
        return response()->json($campus);
    }
}