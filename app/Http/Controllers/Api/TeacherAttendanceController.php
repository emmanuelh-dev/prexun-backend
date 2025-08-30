<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherAttendanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validate([
                'grupo_id' => 'required|exists:grupos,id',
                'date' => 'required|date_format:Y-m-d',
                'attendance' => 'required|array',
            ]);

            $attendanceCount = 0;
            $presentCount = 0;
            $absentCount = 0;
            foreach ($request->attendance as $studentId => $isPresent) {
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

                $attendanceCount++;
                if ($isPresent) {
                    $presentCount++;
                } else {
                    $absentCount++;
                }
            }

            DB::commit();
            echo "\n=== Asistencia guardada ===\n";
            echo "Fecha: {$request->date}\n";
            echo "Grupo ID: {$request->grupo_id}\n";
            echo "Total estudiantes: {$attendanceCount}\n";
            echo "Presentes: {$presentCount}\n";
            echo "Ausentes: {$absentCount}\n";
            echo "Guardado exitosamente a las: " . now()->format('Y-m-d H:i:s') . "\n";
            echo "==========================\n\n";

            Log::info('Asistencia guardada exitosamente', [
                'fecha' => $request->date,
                'grupo_id' => $request->grupo_id,
                'total_estudiantes' => $attendanceCount,
                'presentes' => $presentCount,
                'ausentes' => $absentCount,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Asistencia guardada correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n=== ERROR AL GUARDAR ASISTENCIA ===\n";
            echo "Error: {$e->getMessage()}\n";
            echo "Fecha: {$request->date}\n";
            echo "Grupo ID: {$request->grupo_id}\n";
            echo "Timestamp: " . now()->format('Y-m-d H:i:s') . "\n";
            echo "================================\n\n";

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
