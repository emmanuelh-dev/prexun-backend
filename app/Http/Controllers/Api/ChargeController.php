<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChargeController extends Controller
{
    public function index()
    {
        $charges = Transaction::with('student')
            ->latest()
            ->get();
        return response()->json($charges);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
            'denominations' => 'required_if:payment_method,cash|array',
            'notes' => 'nullable|string|max:255',
        ]);
    
        $validated['denominations'] = json_encode($validated['denominations']);
    
        $validated['transaction_type'] = 'payment';
        $transaction = Transaction::create($validated);
    
        return response()->json($transaction, 201);
    }
    
    public function show($id)
    {
        $charge = Transaction::find($id);
        return $charge;
    }

    public function update($id, Request $request)
    {
        $charge = Transaction::find($id);
        $charge->update($request->all());
        return $charge;
    }

    public function destroy($id)
    {
        $charge = Transaction::find($id);
        $charge->delete();
        return response()->json(['message' => 'Charge deleted successfully']);
    }
}