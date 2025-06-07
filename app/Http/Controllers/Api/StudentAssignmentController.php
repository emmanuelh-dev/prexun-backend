<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentAssignment;
use App\Models\Student;
use App\Models\Period;
use App\Models\Grupo;
use App\Models\SemanaIntensiva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentAssignmentController extends Controller
{
    /**
     * Display a listing of student assignments.
     */
    public function index(Request $request)
    {
        $query = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva']);

        // Apply filters
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->has('period_id')) {
            $query->where('period_id', $request->period_id);
        }

        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }

        if ($request->has('semana_intensiva_id')) {
            $query->where('semana_intensiva_id', $request->semana_intensiva_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Apply scopes
        if ($request->has('only_active') && $request->boolean('only_active')) {
            $query->active();
        }

        if ($request->has('only_current') && $request->boolean('only_current')) {
            $query->current();
        }

        $perPage = (int) $request->query('per_page', 15);
        $page = (int) $request->query('page', 1);

        $assignments = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($assignments);
    }

    /**
     * Store a newly created student assignment.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'period_id' => 'nullable|exists:periods,id',
            'grupo_id' => 'nullable|exists:grupos,id',
            'semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'assigned_at' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:assigned_at',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Set default values
        $validated['assigned_at'] = $validated['assigned_at'] ?? now();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $assignment = StudentAssignment::create($validated);

        return response()->json(
            $assignment->load(['student', 'period', 'grupo', 'semanaIntensiva']),
            201
        );
    }

    /**
     * Display the specified student assignment.
     */
    public function show($id)
    {
        $assignment = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva'])
            ->findOrFail($id);

        return response()->json($assignment);
    }

    /**
     * Update the specified student assignment.
     */
    public function update(Request $request, $id)
    {
        $assignment = StudentAssignment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'student_id' => 'sometimes|exists:students,id',
            'period_id' => 'nullable|exists:periods,id',
            'grupo_id' => 'nullable|exists:grupos,id',
            'semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'assigned_at' => 'sometimes|date',
            'valid_until' => 'nullable|date|after_or_equal:assigned_at',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignment->update($validator->validated());

        return response()->json(
            $assignment->fresh(['student', 'period', 'grupo', 'semanaIntensiva'])
        );
    }

    /**
     * Remove the specified student assignment.
     */
    public function destroy($id)
    {
        $assignment = StudentAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'message' => 'Asignación eliminada correctamente'
        ]);
    }

    /**
     * Get assignments for a specific student.
     */
    public function getByStudent($studentId)
    {
        $assignments = StudentAssignment::with(['period', 'grupo', 'semanaIntensiva'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($assignments);
    }

    /**
     * Get assignments for a specific period.
     */
    public function getByPeriod($periodId)
    {
        $assignments = StudentAssignment::with(['student', 'grupo', 'semanaIntensiva'])
            ->where('period_id', $periodId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($assignments);
    }

    /**
     * Get assignments for a specific grupo.
     */
    public function getByGrupo($grupoId)
    {
        $assignments = StudentAssignment::with(['student', 'period', 'semanaIntensiva'])
            ->where('grupo_id', $grupoId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($assignments);
    }

    /**
     * Get assignments for a specific semana intensiva.
     */
    public function getBySemanaIntensiva($semanaIntensivaId)
    {
        $assignments = StudentAssignment::with(['student', 'period', 'grupo'])
            ->where('semana_intensiva_id', $semanaIntensivaId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($assignments);
    }

    /**
     * Bulk create assignments.
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignments' => 'required|array|min:1',
            'assignments.*.student_id' => 'required|exists:students,id',
            'assignments.*.period_id' => 'nullable|exists:periods,id',
            'assignments.*.grupo_id' => 'nullable|exists:grupos,id',
            'assignments.*.semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'assignments.*.assigned_at' => 'nullable|date',
            'assignments.*.valid_until' => 'nullable|date',
            'assignments.*.is_active' => 'boolean',
            'assignments.*.notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignments = [];
        $now = now();

        foreach ($request->assignments as $assignmentData) {
            $assignmentData['assigned_at'] = $assignmentData['assigned_at'] ?? $now;
            $assignmentData['is_active'] = $assignmentData['is_active'] ?? true;
            
            $assignment = StudentAssignment::create($assignmentData);
            $assignments[] = $assignment->load(['student', 'period', 'grupo', 'semanaIntensiva']);
        }

        return response()->json([
            'message' => 'Asignaciones creadas correctamente',
            'assignments' => $assignments
        ], 201);
    }

    /**
     * Bulk update assignments.
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'exists:student_assignments,id',
            'updates' => 'required|array',
            'updates.period_id' => 'nullable|exists:periods,id',
            'updates.grupo_id' => 'nullable|exists:grupos,id',
            'updates.semana_intensiva_id' => 'nullable|exists:semanas_intensivas,id',
            'updates.valid_until' => 'nullable|date',
            'updates.is_active' => 'sometimes|boolean',
            'updates.notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignmentIds = $request->assignment_ids;
        $updates = $request->updates;

        StudentAssignment::whereIn('id', $assignmentIds)->update($updates);

        $updatedAssignments = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva'])
            ->whereIn('id', $assignmentIds)
            ->get();

        return response()->json([
            'message' => 'Asignaciones actualizadas correctamente',
            'assignments' => $updatedAssignments
        ]);
    }

    /**
     * Toggle active status of assignments.
     */
    public function toggleActive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'exists:student_assignments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $assignments = StudentAssignment::whereIn('id', $request->assignment_ids)->get();

        foreach ($assignments as $assignment) {
            $assignment->update(['is_active' => !$assignment->is_active]);
        }

        $updatedAssignments = StudentAssignment::with(['student', 'period', 'grupo', 'semanaIntensiva'])
            ->whereIn('id', $request->assignment_ids)
            ->get();

        return response()->json([
            'message' => 'Estado de asignaciones actualizado correctamente',
            'assignments' => $updatedAssignments
        ]);
    }
}
