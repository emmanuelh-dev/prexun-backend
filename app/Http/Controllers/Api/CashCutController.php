<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\CashRegister;
use Illuminate\Http\Request;

class CashCutController extends Controller
{
    public function index(Request $request)
    {
        $cashRegisters = CashRegister::with('transactions')->all();
        return response()->json($cashRegisters);
    }

    public function current(Request $request, Campus $campus)
    {
        $cashRegister = CashRegister::where('campus_id', $campus->id)
            ->where('status', 'abierta')
            ->with(['transactions.transactionDetails.denomination', 'gastos'])
            ->first();

        if (!$cashRegister) {
            return response()->json(
                ['message' => 'No hay registro de caja abierto en este campus'],
                404
            );
        }

        $transactions = $cashRegister->transactions->map(function ($transaction) {
            return [
                ...$transaction->toArray(),
                'denominations' => $transaction->transactionDetails->map(function ($detail) {
                    return [
                        'value' => $detail->denomination->value,
                        'type' => $detail->denomination->type,
                        'quantity' => $detail->quantity,
                    ];
                }),
            ];
        });

        $gastos = $cashRegister->gastos->map(function ($gasto) {
            return [
                ...$gasto->toArray(),
                'denominations' => $gasto->gastoDetails->map(function ($detail) {
                    return [
                        'value' => $detail->denomination->value,
                        'type' => $detail->denomination->type,
                        'quantity' => $detail->quantity,
                    ];
                }),
            ];
        });

        $response = [
            ...$cashRegister->toArray(),
            'status' => $cashRegister->status,
            'campus_id' => $cashRegister->campus_id,
            'transactions' => $transactions,
            'gastos' => $gastos,
        ];

        return response()->json($response);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'campus_id' => 'required|exists:campuses,id',
            'initial_amount' => 'required|numeric|min:0',
            'initial_amount_cash' => 'nullable|array',
            'notes' => 'nullable|string|max:255',
            'status' => 'required|in:abierta,cerrada',
            'next_day' => 'nullable',
        ]);
    
        $initialAmountCash = isset($validated['initial_amount_cash']) 
            ? (object)$validated['initial_amount_cash']
            : null;
    
        $cashRegister = CashRegister::create([
            'campus_id' => $validated['campus_id'],
            'initial_amount' => $validated['initial_amount'],
            'initial_amount_cash' => $initialAmountCash ? json_encode($initialAmountCash) : null,
            'notes' => $validated['notes'],
            'opened_at' => now(),
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'cerrada' ? now() : null,
        ]);
    
        return response()->json($cashRegister, 201);
    }

    public function update(Request $request, CashRegister $cashRegister)
    {
        $validated = $request->validate([
            'final_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'status' => 'required|in:abierta,cerrada',
            'final_amount' => 'nullable|numeric|min:0',
            'final_amount_cash' => 'nullable|array',
        ]);
        $finalAmountCash = isset($validated['final_amount_cash'])
            ? (object)$validated['final_amount_cash']
            : null;
        $cashRegister->update([
            'next_day' => null,
            'final_amount' => $validated['final_amount'] ?? $cashRegister->final_amount,
            'notes' => $validated['notes'] ?? $cashRegister->notes,
            'initial_amount_cash' => $finalAmountCash ? json_encode($finalAmountCash) : null,

            'final_amount' => $validated['final_amount'] ?? 0,
            'status' => $validated['status'],
            'closed_at' => now(),
        ]);

        return response()->json($cashRegister, 200);
    }
}
