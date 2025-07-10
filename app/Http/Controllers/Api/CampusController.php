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
            $campuses = Campus::with([
                'users:id,name,email,role',
                'latestCashRegister',
                'grupos'
            ])->get();
        } else {
            $campuses = $user->campuses()->with([
                'users:id,name,email,role',
                'latestCashRegister',
                'grupos'
            ])->get();
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
            'admin_ids.*' => 'exists:users,id',
            'folio_inicial' => 'nullable|integer',
            'titular' => 'nullable|string',
            'grupo_ids' => 'nullable|array',  // Cambiado de 'grupos' a 'grupo_ids'
            'grupo_ids.*' => 'exists:grupos,id'  // Validación para cada ID
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $campus = Campus::create($request->only([
            'name', 'code', 'description', 'address',
            'is_active', 'folio_inicial', 'titular'
        ]));

        if ($request->has('admin_ids')) {
            $campus->users()->sync($request->admin_ids);
        }

        if ($request->has('grupo_ids')) {
            $campus->grupos()->sync($request->grupo_ids);
        }

        $campus->load(['users:id,name,email,role', 'grupos']);

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
            'admin_ids.*' => 'exists:users,id',
            'folio_inicial' => 'nullable|integer',
            'titular' => 'nullable|string',
            'grupo_ids' => 'nullable|array',  
            'grupo_ids.*' => 'exists:grupos,id'  
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $campus->update($request->only([
            'name', 'code', 'description', 'address',
            'is_active', 'folio_inicial', 'titular'
        ]));

        if ($request->has('admin_ids')) {
            $campus->users()->sync($request->input('admin_ids'));
        }

        if ($request->has('grupo_ids')) {
            $campus->grupos()->sync($request->input('grupo_ids'));
        }

        $campus->load(['users:id,name,email,role', 'grupos']);

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
