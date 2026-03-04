<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentAssignment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PublicAttendanceController extends Controller
{
    /**
     * Registrar asistencia mediante número de teléfono
     */
    public function registerByPhone(Request $request)
    {
        $request->validate([
            'phone' => 'nullable|string|required_without:whatsapp',
            'whatsapp' => 'nullable|string|required_without:phone',
            'campus_id' => 'nullable|integer|exists:campuses,id',
        ]);

        $rawPhone = $request->input('whatsapp') ?: $request->input('phone');
        $phone = $this->normalizePhone($rawPhone);
        $campusId = $request->input('campus_id');

        if (strlen($phone) < 10) {
            return response()->json([
                'success' => false,
                'message' => 'Número de teléfono inválido. Debe contener al menos 10 dígitos.'
            ], 422);
        }

        $phoneDigits = substr($phone, -10);

        $phoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', ''), ',', '')";
        $tutorPhoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(tutor_phone, ''), ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', ''), ',', '')";

        $baseStudentQuery = Student::query()
            ->where(function ($query) use ($phoneDigits, $phoneSql, $tutorPhoneSql) {
                $query
                    ->whereRaw("RIGHT({$phoneSql}, 10) = ?", [$phoneDigits])
                    ->orWhereRaw("RIGHT({$tutorPhoneSql}, 10) = ?", [$phoneDigits]);
            });

        $studentQuery = clone $baseStudentQuery;
        if ($campusId) {
            $studentQuery->where('campus_id', $campusId);
        }

        $student = $studentQuery->first();

        if (!$student && $campusId) {
            $studentInOtherCampus = (clone $baseStudentQuery)->first();

            if ($studentInOtherCampus) {
                Log::warning('Public attendance campus mismatch', [
                    'input_phone' => $rawPhone,
                    'normalized_phone' => $phone,
                    'last_ten_digits' => $phoneDigits,
                    'requested_campus_id' => (int) $campusId,
                    'matched_student_id' => $studentInOtherCampus->id,
                    'matched_student_campus_id' => $studentInOtherCampus->campus_id,
                    'matched_student_phone' => $studentInOtherCampus->phone,
                    'matched_student_tutor_phone' => $studentInOtherCampus->tutor_phone,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El número existe, pero no corresponde al plantel configurado en este enlace.',
                    'code' => 'CAMPUS_MISMATCH',
                ], 422);
            }
        }

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Estudiante no encontrado con el número proporcionado.'
            ], 404);
        }

        $assignmentQuery = StudentAssignment::query()
            ->active()
            ->current()
            ->where('student_id', $student->id)
            ->whereNotNull('grupo_id')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');

        if ($campusId) {
            $assignmentQuery->whereHas('grupo', function ($query) use ($campusId) {
                $query->where(function ($grupoQuery) use ($campusId) {
                    $grupoQuery
                        ->where('plantel_id', $campusId)
                        ->orWhereHas('campuses', function ($campusQuery) use ($campusId) {
                            $campusQuery->where('campuses.id', $campusId);
                        });
                });
            });
        }

        $assignment = $assignmentQuery->first();
        $grupoId = $assignment?->grupo_id;

        if (!$grupoId) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante ' . $student->firstname . ' ' . $student->lastname . ' no tiene un grupo asignado.',
                'student' => [
                    'name' => $student->firstname . ' ' . $student->lastname,
                    'matricula' => $student->matricula ?: $student->id
                ]
            ], 422);
        }

        $today = Carbon::now('America/Mexico_City')->toDateString();
        
        // Verificar si ya tiene asistencia hoy
        $existing = Attendance::where('student_id', $student->id)
            ->where('date', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'La asistencia ya estaba registrada para hoy.',
                'already_registered' => true,
                'student' => [
                    'name' => $student->firstname . ' ' . $student->lastname,
                    'matricula' => $student->matricula ?: $student->id,
                    'phone' => $student->phone
                ],
                'attendance' => $existing
            ]);
        }

        // Crear el registro de asistencia
        $attendance = Attendance::create([
            'student_id' => $student->id,
            'grupo_id' => $grupoId,
            'date' => $today,
            'present' => true,
            'attendance_time' => Carbon::now('America/Mexico_City'),
            'notes' => 'Registrado vía API pública (WhatsApp)'
        ]);

        Log::info('Asistencia pública registrada', [
            'student_id' => $student->id,
            'phone' => $phone,
            'grupo_id' => $grupoId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asistencia registrada correctamente.',
            'already_registered' => false,
            'student' => [
                'name' => $student->firstname . ' ' . $student->lastname,
                'matricula' => $student->matricula ?: $student->id,
                'phone' => $student->phone
            ],
            'attendance' => $attendance
        ]);
    }

    /**
     * Normalizar el número de teléfono para la búsqueda
     */
    private function normalizePhone($phone)
    {
        // Quitar caracteres no numéricos
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        
        // Si tiene 12 dígitos y empieza con 52 (México), tomamos los últimos 10
        if (strlen($normalized) > 10) {
            $normalized = substr($normalized, -10);
        }
        
        return $normalized;
    }
}
