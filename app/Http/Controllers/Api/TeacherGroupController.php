<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TeacherGroupController extends Controller
{
    public function getTeacherGroups($id)
    {
        try {
            $teacher = User::findOrFail($id);
            
            if ($teacher->role !== 'maestro' && $teacher->role !== 'teacher') {
                return response()->json([
                    'message' => 'El usuario no es un maestro'
                ], 400);
            }

            $grupos = $teacher->grupos()
                ->with(['students' => function($query) {
                    $query->select(
                        'students.id',
                        'students.firstname',
                        'students.lastname',
                        'students.email',
                        'students.matricula',
                        'students.grupo_id'
                    );
                }])
                ->get();
            
            // Ya no transformamos el ID, dejamos que use el ID original del estudiante
            return response()->json($grupos);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener grupos del maestro: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener grupos del maestro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function assignGroups(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $teacher = User::findOrFail($id);
            
            if ($teacher->role !== 'teacher' && $teacher->role !== 'maestro') {
                return response()->json([
                    'message' => 'El usuario no es un maestro'
                ], 400);
            }

            $request->validate([
                'grupo_ids' => 'required|array',
                'grupo_ids.*' => 'exists:grupos,id'
            ]);

            $teacher->grupos()->sync($request->grupo_ids);

            DB::commit();
            return response()->json([
                'message' => 'Grupos asignados correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning groups to teacher:', [
                'teacher_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al asignar grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
