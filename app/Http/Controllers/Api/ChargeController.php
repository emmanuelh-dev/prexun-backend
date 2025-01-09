<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Denomination;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ChargeController extends Controller
{
    public function index(Request $request)
    {

        $campus_id = $request->campus_id;
        $charges = Transaction::with('student')
            ->where('campus_id', $campus_id)
            ->with('student', 'campus', 'student.grupo')
            ->get();
        return response()->json($charges);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'student_id' => 'required|exists:students,id',
        'campus_id' => 'required|exists:campuses,id',
        'amount' => 'required|numeric|min:0',
        'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
        'denominations' => 'required_if:payment_method,cash|array',
        'notes' => 'nullable|string|max:255',
        'paid' => 'required|boolean'
    ]);

    try {
        return DB::transaction(function () use ($validated) {
            // Crear la transacción principal
            $transaction = Transaction::create([
                'student_id' => $validated['student_id'],
                'campus_id' => $validated['campus_id'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'paid' => $validated['paid'],
                'transaction_type' => 'payment',
                'uuid' => Str::uuid(),
            ]);

            // Si el pago es en efectivo, guardar las denominaciones
            if ($validated['payment_method'] === 'cash' && !empty($validated['denominations'])) {
                // Iterar sobre el formato {valor: cantidad}
                foreach ($validated['denominations'] as $value => $quantity) {
                    // Obtener o crear la denominación si no existe
                    $denomination = Denomination::firstOrCreate(
                        ['value' => $value],
                        ['type' => $value >= 100 ? 'billete' : 'moneda']
                    );

                    // Solo crear el detalle si la cantidad es mayor a 0
                    if ($quantity > 0) {
                        TransactionDetail::create([
                            'transaction_id' => $transaction->id,
                            'denomination_id' => $denomination->id,
                            'quantity' => $quantity
                        ]);
                    }
                }
            }

            // Cargar los detalles de denominaciones en la respuesta
            return response()->json(
                $transaction->load('transactionDetails.denomination'),
                201
            );
        });
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al procesar la transacción',
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function all()
    {
        $charges = Transaction::with('student', 'campus', 'student.grupo')->get();
        return response()->json($charges);
    }

    public function show($id)
    {
        $charge = Transaction::with('student', 'campus')->findOrFail($id)->load('student', 'campus', 'student.grupo');
        return $charge;
    }

    public function showByUuid($uuid)
    {
        return Transaction::with(['student', 'campus', 'student.grupo'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function update($id, Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'campus_id' => 'nullable|exists:campuses,id',
            'amount' => 'nullable|numeric|min:0',
            'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'card'])],
            'denominations' => 'nullable|array',
            'notes' => 'nullable|string|max:255',
            'paid' => 'nullable|boolean',
            'cash_register_id' => 'nullable|exists:cash_registers,id'
        ]);
    
        try {
            return DB::transaction(function () use ($id, $validated) {
                $transaction = Transaction::findOrFail($id);
    
                // Actualizar los campos de la transacción principal
                $transaction->update($validated);
    
                // Si se actualiza el pago como efectivo y hay denominaciones, procesarlas
                if (isset($validated['payment_method']) && $validated['payment_method'] === 'cash' && isset($validated['denominations'])) {
                    // Eliminar detalles existentes relacionados con denominaciones
                    $transaction->transactionDetails()->delete();
    
                    // Guardar nuevas denominaciones
                    foreach ($validated['denominations'] as $value => $quantity) {
                        if ($quantity > 0) {
                            $denomination = Denomination::firstOrCreate(
                                ['value' => $value],
                                ['type' => $value >= 100 ? 'billete' : 'moneda']
                            );
    
                            TransactionDetail::create([
                                'transaction_id' => $transaction->id,
                                'denomination_id' => $denomination->id,
                                'quantity' => $quantity
                            ]);
                        }
                    }
                }
    
                return response()->json(
                    $transaction->load('transactionDetails.denomination'),
                    200
                );
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la transacción',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    public function destroy($id)
    {
        $charge = Transaction::find($id);
        $charge->delete();
        return response()->json(['message' => 'Charge deleted successfully']);
    }
}
