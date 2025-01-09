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
            ->with('transactions')
            ->first();

        if (!$cashRegister) {
            return response()->json(
                ['message' => 'No hay registro de caja abierto en este campus'],
                404
            );
        }

        return response()->json($cashRegister);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campus_id' => 'required|exists:campuses,id',
            'initial_amount' => 'required|numeric|min:0',
            'final_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'status' => 'required|in:abierta,cerrada'
        ]);

        $cashRegister = CashRegister::create([
            'campus_id' => $validated['campus_id'],
            'initial_amount' => $validated['initial_amount'],
            'final_amount' => $validated['final_amount'] ?? 0,
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
            'status' => 'required|in:abierta,cerrada'
        ]);

        $cashRegister->update([
            'final_amount' => $validated['final_amount'] ?? $cashRegister->final_amount,
            'notes' => $validated['notes'] ?? $cashRegister->notes,
            'status' => $validated['status'],
            'closed_at' => now(),
        ]);

        return response()->json($cashRegister, 200);
    }
}
