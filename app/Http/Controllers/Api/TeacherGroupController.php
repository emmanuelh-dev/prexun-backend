<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeacherGroupController extends Controller
{
    public function getTeacherGroups($id)
    {
        try {
            $teacher = User::with('grupos')->findOrFail($id);
            
            if ($teacher->role !== 'maestro') {
                return response()->json([
                    'message' => 'El usuario no es un maestro'
                ], 400);
            }

            $grupos = $teacher->grupos()->get();
            
            return response()->json($grupos);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener grupos del maestro: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener grupos del maestro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function assignGroups(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'grupo_ids' => 'required|array',
                'grupo_ids.*' => 'exists:grupos,id'
            ]);

            $user = User::findOrFail($request->user_id);
            $user->grupos()->sync($request->grupo_ids);

            return response()->json([
                'message' => 'Grupos asignados correctamente',
                'grupos' => $user->grupos
            ]);
        } catch (\Exception $e) {
            Log::error('Error al asignar grupos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al asignar grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}