<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
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
            ->where('paid', true)
            ->with('student', 'campus', 'student.grupo')
            ->orderBy('folio', 'desc')
            ->get();
        $charges->transform(function ($charge) {
            if ($charge->image) {
                $charge->image = asset('storage/' . $charge->image);
            }
            return $charge;
        });
        return response()->json($charges);
    }


    public function notPaid(Request $request)
    {
        $campus_id = $request->query('campus_id');
        $expiration_date = $request->query('expiration_date');
    
        if (!$campus_id) {
            return response()->json(['error' => 'campus_id is required'], 400);
        }
    
        $query = Transaction::with('student', 'campus', 'student.grupo')
            ->where('campus_id', $campus_id)
            ->where('paid', false)
            ->orderBy('expiration_date', 'asc');
    
            if ($expiration_date) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
                    return response()->json(['error' => 'Invalid date format (YYYY-MM-DD)'], 400);
                }
                $query->whereDate('expiration_date', $expiration_date);
            }
    
        $charges = $query->get();
    
        $charges = $charges->map(function ($charge) {
            if ($charge->image) {
                $charge->image = asset('storage/' . $charge->image);
            }
            return $charge;
        });
    
        return response()->json($charges);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'campus_id' => 'required|exists:campuses,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
            'expiration_date' => 'required',
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
                    'expiration_date' => $validated['expiration_date'],
                    'uuid' => Str::uuid(),
                ]);

                if ($validated['payment_method'] === 'cash' && !empty($validated['denominations'])) {
                    foreach ($validated['denominations'] as $value => $quantity) {
                        $denomination = Denomination::firstOrCreate(
                            ['value' => $value],
                            ['type' => $value >= 100 ? 'billete' : 'moneda']
                        );

                        if ($quantity > 0) {
                            TransactionDetail::create([
                                'transaction_id' => $transaction->id,
                                'denomination_id' => $denomination->id,
                                'quantity' => $quantity
                            ]);
                        }
                    }
                }

                if ($transaction->image) {
                    $transaction->image = asset('storage/' . $transaction->image);
                }
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
            'cash_register_id' => 'nullable|exists:cash_registers,id',
            'payment_date' => 'nullable|date_format:Y-m-d',
            'image' => 'nullable|image',
            'card_id' => 'nullable|exists:cards,id'
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('transactions', 'public');
        }

        try {
            return DB::transaction(function () use ($id, $validated) {

                $campus = Campus::find($validated['campus_id']);
                $folioCampus = $campus->folio_inicial;
                $folioActual = Transaction::where('campus_id', $validated['campus_id'])
                    ->whereNotNull('folio')
                    ->max('folio');           
                $folio = max($folioCampus, $folioActual ?: 0);
                
                $validated['folio'] = $folio + 1;

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

                if ($transaction->image) {
                    $transaction->image = asset('storage/' . $transaction->image);
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
