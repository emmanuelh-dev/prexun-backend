<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Student;
use App\Models\Period;
use App\Models\StudentAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DebtController extends Controller
{
  public function index(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'student_id' => 'nullable|exists:students,id',
      'period_id' => 'nullable|exists:periods,id',
      'assignment_id' => 'nullable|exists:student_assignments,id',
      'status' => 'nullable|in:pending,partial,paid,overdue',
      'campus_id' => 'nullable|exists:campuses,id',
      'search' => 'nullable|string|max:255',
      'per_page' => 'nullable|integer|min:1|max:100',
      'page' => 'nullable|integer|min:1'
    ]);

    $query = Debt::with(['student', 'period', 'assignment.period', 'assignment.grupo', 'assignment.semanaIntensiva', 'transactions'])
      ->orderBy('due_date', 'asc');

    // Filtros
    if ($validated['student_id'] ?? null) {
      $query->where('student_id', $validated['student_id']);
    }

    if ($validated['period_id'] ?? null) {
      $query->where('period_id', $validated['period_id']);
    }

    if ($validated['assignment_id'] ?? null) {
      $query->where('assignment_id', $validated['assignment_id']);
    }

    if ($validated['status'] ?? null) {
      $query->where('status', $validated['status']);
    }

    if ($validated['campus_id'] ?? null) {
      $query->whereHas('student', function ($q) use ($validated) {
        $q->where('campus_id', $validated['campus_id']);
      });
    }

    if ($validated['search'] ?? null) {
      $search = $validated['search'];
      $query->where(function ($q) use ($search) {
        $q->where('concept', 'like', "%{$search}%")
          ->orWhere('description', 'like', "%{$search}%")
          ->orWhereHas('student', function ($studentQuery) use ($search) {
            $studentQuery->where('firstname', 'like', "%{$search}%")
              ->orWhere('lastname', 'like', "%{$search}%")
              ->orWhere('matricula', 'like', "%{$search}%");
          });
      });
    }

    $perPage = $validated['per_page'] ?? 15;
    $debts = $query->paginate($perPage);

    return response()->json($debts);
  }

  public function store(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'student_id' => 'required|exists:students,id',
      'period_id' => 'nullable|exists:periods,id',
      'assignment_id' => 'nullable|exists:student_assignments,id',
      'concept' => 'required|string|max:255',
      'total_amount' => 'required|numeric|min:0',
      'due_date' => 'required|date|after_or_equal:today',
      'description' => 'nullable|string|max:1000'
    ]);

    // Validar que al menos uno de period_id o assignment_id esté presente
    if (empty($validated['period_id']) && empty($validated['assignment_id'])) {
      return response()->json([
        'message' => 'Debe especificar un período o una asignación',
        'errors' => ['assignment_id' => ['Debe seleccionar una asignación o período']]
      ], 422);
    }

    try {
      $debt = DB::transaction(function () use ($validated) {
        $debt = Debt::create([
          'student_id' => $validated['student_id'],
          'period_id' => $validated['period_id'] ?? null,
          'assignment_id' => $validated['assignment_id'] ?? null,
          'concept' => $validated['concept'],
          'total_amount' => $validated['total_amount'],
          'remaining_amount' => $validated['total_amount'],
          'due_date' => $validated['due_date'],
          'description' => $validated['description'] ?? null,
          'status' => 'pending'
        ]);

        return $debt->load(['student', 'period', 'assignment.period', 'assignment.grupo', 'assignment.semanaIntensiva']);
      });

      return response()->json([
        'message' => 'Adeudo creado exitosamente',
        'debt' => $debt
      ], 201);
    } catch (\Exception $e) {
      return response()->json([
        'message' => 'Error al crear el adeudo',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function show($id): JsonResponse
  {
    $debt = Debt::with(['student', 'period', 'assignment.period', 'assignment.grupo', 'assignment.semanaIntensiva', 'transactions.cashRegister'])
      ->findOrFail($id);

    return response()->json($debt);
  }

  public function update(Request $request, $id): JsonResponse
  {
    $debt = Debt::findOrFail($id);

    $validated = $request->validate([
      'concept' => 'sometimes|string|max:255',
      'total_amount' => 'sometimes|numeric|min:0',
      'due_date' => 'sometimes|date',
      'description' => 'nullable|string|max:1000',
      'status' => ['sometimes', Rule::in(['pending', 'partial', 'paid', 'overdue'])],
      'assignment_id' => 'sometimes|nullable|exists:student_assignments,id',
      'period_id' => 'sometimes|nullable|exists:periods,id'
    ]);

    try {
      DB::transaction(function () use ($debt, $validated) {
        // Si se actualiza el monto total, recalcular el monto restante
        if (isset($validated['total_amount'])) {
          $validated['remaining_amount'] = $validated['total_amount'] - $debt->paid_amount;
        }

        $debt->update($validated);
        $debt->updatePaymentStatus();
      });

      return response()->json([
        'message' => 'Adeudo actualizado exitosamente',
        'debt' => $debt->load(['student', 'period', 'assignment.period', 'assignment.grupo', 'assignment.semanaIntensiva'])
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'message' => 'Error al actualizar el adeudo',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function destroy($id): JsonResponse
  {
    $debt = Debt::findOrFail($id);

    // Verificar si tiene transacciones asociadas
    if ($debt->transactions()->exists()) {
      return response()->json([
        'message' => 'No se puede eliminar un adeudo que tiene transacciones asociadas'
      ], 422);
    }

    try {
      $debt->delete();

      return response()->json([
        'message' => 'Adeudo eliminado exitosamente'
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'message' => 'Error al eliminar el adeudo',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function getByStudent($studentId): JsonResponse
  {
    $student = Student::findOrFail($studentId);

    $debts = Debt::with(['period', 'assignment.period', 'assignment.grupo', 'assignment.semanaIntensiva', 'transactions'])
      ->where('student_id', $studentId)
      ->orderBy('due_date', 'asc')
      ->get();

    return response()->json([
      'student' => $student,
      'debts' => $debts
    ]);
  }

  public function getByCampus($campusId): JsonResponse
  {
    $debts = Debt::with(['student', 'period', 'assignment.period', 'assignment.grupo', 'assignment.semanaIntensiva', 'transactions'])
      ->whereHas('student', function ($query) use ($campusId) {
        $query->where('campus_id', $campusId);
      })
      ->orderBy('due_date', 'asc')
      ->get();

    return response()->json($debts);
  }

  public function getByPeriod($periodId): JsonResponse
  {
    $period = Period::findOrFail($periodId);

    $debts = Debt::with(['student', 'assignment.grupo', 'assignment.semanaIntensiva', 'transactions'])
      ->where('period_id', $periodId)
      ->orderBy('due_date', 'asc')
      ->get();

    return response()->json([
      'period' => $period,
      'debts' => $debts
    ]);
  }

  public function getByAssignment($assignmentId): JsonResponse
  {
    $assignment = StudentAssignment::with(['period', 'grupo', 'semanaIntensiva'])->findOrFail($assignmentId);

    $debts = Debt::with(['student', 'period', 'transactions'])
      ->where('assignment_id', $assignmentId)
      ->orderBy('due_date', 'asc')
      ->get();

    return response()->json([
      'assignment' => $assignment,
      'debts' => $debts
    ]);
  }

  public function updatePaymentStatus($id): JsonResponse
  {
    $debt = Debt::findOrFail($id);

    try {
      $debt->updatePaymentStatus();

      return response()->json([
        'message' => 'Estado de pago actualizado exitosamente',
        'debt' => $debt->load(['student', 'period', 'transactions'])
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'message' => 'Error al actualizar el estado de pago',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function getOverdueDebts(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'campus_id' => 'nullable|exists:campuses,id',
      'period_id' => 'nullable|exists:periods,id'
    ]);

    $query = Debt::with(['student', 'period'])
      ->where('status', '!=', 'paid')
      ->where('due_date', '<', now())
      ->orderBy('due_date', 'asc');

    if ($validated['campus_id'] ?? null) {
      $query->whereHas('student', function ($q) use ($validated) {
        $q->where('campus_id', $validated['campus_id']);
      });
    }

    if ($validated['period_id'] ?? null) {
      $query->where('period_id', $validated['period_id']);
    }

    $overdueDebts = $query->get();

    return response()->json($overdueDebts);
  }

  public function getDebtSummary(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'campus_id' => 'nullable|exists:campuses,id',
      'period_id' => 'nullable|exists:periods,id'
    ]);

    $query = Debt::query();

    if ($validated['campus_id'] ?? null) {
      $query->whereHas('student', function ($q) use ($validated) {
        $q->where('campus_id', $validated['campus_id']);
      });
    }

    if ($validated['period_id'] ?? null) {
      $query->where('period_id', $validated['period_id']);
    }

    $summary = [
      'total_debts' => $query->count(),
      'pending_debts' => $query->clone()->where('status', 'pending')->count(),
      'partial_debts' => $query->clone()->where('status', 'partial')->count(),
      'paid_debts' => $query->clone()->where('status', 'paid')->count(),
      'overdue_debts' => $query->clone()->where('status', 'overdue')->count(),
      'total_amount' => $query->clone()->sum('total_amount'),
      'paid_amount' => $query->clone()->sum('paid_amount'),
      'remaining_amount' => $query->clone()->sum('remaining_amount')
    ];

    return response()->json($summary);
  }
}
