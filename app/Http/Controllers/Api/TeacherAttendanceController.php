<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherAttendanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validación de datos
            $validatedData = $request->validate([
                'grupo_id' => 'required|exists:grupos,id',
                'date' => 'required|date_format:Y-m-d',
                'attendance' => 'required|array',
                // Nota: No validamos attendance.* porque las claves son dinámicas
            ]);

            // Procesar cada asistencia
            foreach ($request->attendance as $studentId => $isPresent) {

                // Validación manual del tipo booleano
                if (!is_bool($isPresent)) {
                    throw new \Exception("El valor de asistencia para el estudiante $studentId no es un booleano.");
                }

                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'grupo_id' => $request->grupo_id,
                        'date' => $request->date,
                    ],
                    [
                        'present' => $isPresent
                    ]
                );

                if (!$attendance) {
                    throw new \Exception('Error al guardar la asistencia para el estudiante ' . $studentId);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asistencia guardada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar asistencia:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la asistencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAttendance($grupo_id, $date)
    {
        try {
            $attendance = Attendance::with('student')
                ->where('grupo_id', $grupo_id)
                ->where('date', $date)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener asistencia:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la asistencia',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
