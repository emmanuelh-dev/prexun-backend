<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
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
        ]);
    
        $validated['denominations'] = json_encode($validated['denominations']);
        $validated['transaction_type'] = 'payment';
        $validated['uuid'] = Str::uuid();
        $transaction = Transaction::create($validated);
    
        return response()->json($transaction, 201);
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
