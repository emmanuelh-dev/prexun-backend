<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
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
        'date' => 'required|date',
        'attendance' => 'required|array',
      ]);

      $attendanceCount = 0;
      $presentCount = 0;
      $absentCount = 0;

      foreach ($request->attendance as $record) {
        $studentId = $record['student_id'];
        $isPresent = $record['present'];
        $attendanceTime = $record['attendance_time'] ?? now()->toISOString();
        $notes = $record['notes'] ?? null;

        $attendance = Attendance::updateOrCreate(
          [
            'student_id' => $studentId,
            'grupo_id' => $request->grupo_id,
            'date' => $request->date,
          ],
          [
            'present' => $isPresent,
            'attendance_time' => $attendanceTime,
            'notes' => $notes
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

  public function findStudent(Student $student)
  {
    Log::info("Buscando estudiante con ID o matrícula: {$student->id}");

    $student->load([
      'assignments.grupo',
      'assignments.semanaIntensiva'
    ]);

    return response()->json([
      'success' => true,
      'data' => $student
    ]);
  }


  public function quickStore(Request $request)
  {
    try {
      $validated = $request->validate([
        'student_id' => 'required|exists:students,id',
        'date' => 'required|date',
        'present' => 'required|boolean',
        'attendance_time' => 'nullable|date'
      ]);

      $student = Student::with('assignments')->findOrFail($validated['student_id']);

      // Procesar la fecha para asegurar formato consistente
      $date = $validated['date'];
      // Si viene en formato ISO 8601, extraer solo la fecha
      if (strpos($date, 'T') !== false) {
        $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
      }

      if (!$student->assignments) {
        throw new \Exception('El estudiante no tiene asignaciones.');
      }

      $attendance = Attendance::updateOrCreate(
        [
          'student_id' => $validated['student_id'],
          'grupo_id' => $student->assignments->first()->grupo_id,
          'date' => $date,
          'attendance_time' => $validated['attendance_time'] ?? null,
        ],
        [
          'present' => $validated['present'],
        ]
      );

      Log::info('Asistencia rápida guardada exitosamente', [
        'attendance_id' => $attendance->id,
        'student_id' => $validated['student_id'],
        'grupo_id' => $student->grupo->id,
        'fecha' => $date,
        'presente' => $validated['present'],
        'timestamp' => now()->toISOString()
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Asistencia guardada correctamente',
        'data' => $attendance
      ]);
    } catch (\Exception $e) {
      Log::error('Error al guardar asistencia rápida:', [
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error al guardar la asistencia: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function updateAttendance(Request $request, $attendanceId)
  {
    try {
      $validated = $request->validate([
        'present' => 'required|boolean',
        'notes' => 'nullable|string|max:500',
        'attendance_time' => 'nullable|date'
      ]);

      $attendance = Attendance::findOrFail($attendanceId);

      $attendance->update([
        'present' => $validated['present'],
        'notes' => $validated['notes'],
        'attendance_time' => $validated['attendance_time'] ?? $attendance->attendance_time
      ]);

      Log::info('Asistencia actualizada', [
        'attendance_id' => $attendanceId,
        'student_id' => $attendance->student_id,
        'presente' => $validated['present'],
        'notas' => $validated['notes'],
        'timestamp' => now()->toISOString()
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Asistencia actualizada correctamente',
        'data' => $attendance->load('student')
      ]);
    } catch (\Exception $e) {
      Log::error('Error al actualizar asistencia:', [
        'error' => $e->getMessage(),
        'attendance_id' => $attendanceId
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error al actualizar la asistencia',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function getTodayAttendance($date)
  {
    try {
      $attendance = Attendance::with(['student', 'grupo'])
        ->where('date', $date)
        ->orderBy('updated_at', 'desc')
        ->get();

      return response()->json([
        'success' => true,
        'data' => $attendance
      ]);
    } catch (\Exception $e) {
      Log::error('Error al obtener asistencias del día:', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error al obtener las asistencias del día',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Generar reporte de asistencia de un estudiante
   * Calcula días presentes y ausentes en un rango de fechas
   */
  public function getStudentAttendanceReport($studentID, Request $request)
  {
    
    $student = Student::with(['grupo.period'])->find($studentID);

    try {
      $excludeWeekends = $request->exclude_weekends ?? true;

      // Obtener todos los registros de asistencia del estudiante
      $attendanceRecords = Attendance::where('student_id', $student->id)
        ->orderBy('date')
        ->get();

      return response()->json([
        'success' => true,
        'data' => $attendanceRecords
      ]);
    } catch (\Exception $e) {
      Log::error('Error al generar reporte de asistencia:', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error al generar el reporte de asistencia',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  private function getEmptyReport($student, $excludeWeekends)
  {
    // Cargar relaciones si es necesario para el reporte
    $student->loadMissing('grupo.period');

    return [
      'student' => [
        'id' => $student->id,
        'firstname' => $student->firstname,
        'lastname' => $student->lastname,
        'matricula' => $student->matricula,
        'grupo' => $student->grupo ? $student->grupo->name : null,
        'period' => $student->grupo && $student->grupo->period ? $student->grupo->period->name : null,
      ],
      'period' => [
        'start_date' => null,
        'end_date' => null,
        'total_days' => 0,
        'exclude_weekends' => $excludeWeekends,
      ],
      'statistics' => [
        'present_count' => 0,
        'absent_count' => 0,
        'total_days' => 0,
        'attendance_percentage' => 0,
        'absent_percentage' => 0,
      ],
      'attendance_details' => [
        'all_days' => [],
        'present_days' => [],
        'absent_days' => [],
      ]
    ];
  }
  /**
   * Generar reporte de asistencia de un grupo completo
   */
  public function getGroupAttendanceReport($groupId, Request $request)
  {
    try {
      $validatedData = $request->validate([
        'start_date' => 'required|date_format:Y-m-d',
        'end_date' => 'required|date_format:Y-m-d',
        'exclude_weekends' => 'boolean',
      ]);

      $grupo = \App\Models\Grupo::with(['students', 'period'])->findOrFail($groupId);
      $excludeWeekends = $request->exclude_weekends ?? true;

      $studentsReports = [];

      foreach ($grupo->students as $student) {
        // Crear una request temporal para cada estudiante
        $studentRequest = new Request([
          'start_date' => $request->start_date,
          'end_date' => $request->end_date,
          'exclude_weekends' => $excludeWeekends,
        ]);

        $studentReportResponse = $this->getStudentAttendanceReport($student->id, $studentRequest);
        $studentReportData = json_decode($studentReportResponse->getContent(), true);

        if ($studentReportData['success']) {
          $studentsReports[] = $studentReportData['data'];
        }
      }

      // Calcular estadísticas del grupo
      $totalStudents = count($studentsReports);
      $totalPresentDays = array_sum(array_column(array_column($studentsReports, 'statistics'), 'present_count'));
      $totalAbsentDays = array_sum(array_column(array_column($studentsReports, 'statistics'), 'absent_count'));
      $totalPossibleDays = array_sum(array_column(array_column($studentsReports, 'statistics'), 'total_days'));

      $groupAttendancePercentage = $totalPossibleDays > 0 ? round(($totalPresentDays / $totalPossibleDays) * 100, 2) : 0;

      $groupReport = [
        'group' => [
          'id' => $grupo->id,
          'name' => $grupo->name,
          'period' => $grupo->period ? $grupo->period->name : null,
          'total_students' => $totalStudents,
        ],
        'period' => [
          'start_date' => $request->start_date,
          'end_date' => $request->end_date,
          'exclude_weekends' => $excludeWeekends,
        ],
        'group_statistics' => [
          'total_students' => $totalStudents,
          'total_present_days' => $totalPresentDays,
          'total_absent_days' => $totalAbsentDays,
          'total_possible_days' => $totalPossibleDays,
          'group_attendance_percentage' => $groupAttendancePercentage,
        ],
        'students_reports' => $studentsReports,
      ];

      return response()->json([
        'success' => true,
        'data' => $groupReport
      ]);
    } catch (\Exception $e) {
      Log::error('Error al generar reporte de grupo:', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error al generar el reporte del grupo',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Helper para obtener nombres de días en español
   */
  private function getDayNameInSpanish($dayNumber)
  {
    $days = [
      1 => 'Lunes',
      2 => 'Martes',
      3 => 'Miércoles',
      4 => 'Jueves',
      5 => 'Viernes',
      6 => 'Sábado',
      7 => 'Domingo'
    ];

    return $days[$dayNumber] ?? 'Desconocido';
  }
}
