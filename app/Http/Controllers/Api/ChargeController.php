<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Denomination;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ChargeController extends Controller
{

    public function index(Request $request)
    {
        $campus_id = $request->campus_id; 
        $perPage = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);
    
        $charges = Transaction::with(['student', 'campus', 'student.grupo'])
            ->where('campus_id', $campus_id)
            ->where('paid', true)
            ->orderBy('payment_date', 'desc')
            ->orderBy('folio', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    
        $charges->getCollection()->each(function ($charge) {
            if ($charge->image) {
                $charge->image = asset('storage/' . $charge->image);
            }
        });
    
        return response()->json($charges, 200);
    }
    
    
    
    public function notPaid(Request $request)
    {
        $campus_id = $request->query('campus_id');
        $expiration_date = $request->query('expiration_date');
        $perPage = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);

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

        $charges = $query->paginate($perPage, ['*'], 'page', $page);

        $charges->getCollection()->transform(function ($charge) {
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
        return Transaction::with(['student', 'campus', 'student.grupo', 'card'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
    
    public function updateFolio(Request $request, $id)
    {
        $request->validate([
            'folio' => 'required|integer|min:1',
        ]);

        $transaction = Transaction::findOrFail($id);

        $transaction->folio = $request->folio;
        $transaction->save();

        return response()->json($transaction, 200);
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
            'card_id' => 'nullable|exists:cards,id',
            'sat' => 'nullable|boolean'
        ]);
    
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('transactions', 'public');
        }
    
        try {
            return DB::transaction(function () use ($id, $validated) {
                $transaction = Transaction::findOrFail($id);
    
                $transaction->folio = $this->generateMonthlyFolio($validated['campus_id']);
    
                $transaction->update($validated);
    
                if (isset($validated['payment_method']) && $validated['payment_method'] === 'cash' && isset($validated['denominations'])) {
                    $transaction->transactionDetails()->delete();
    
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
    
    /**
     * Genera un folio con reinicio mensual
     */
    private function generateMonthlyFolio($campusId)
    {
        // Obtenemos la fecha actual
        $now = now();
        $currentMonth = $now->month;
        $currentYear = $now->year;
        
        // Buscamos el último folio para este campus
        $lastTransaction = Transaction::where('campus_id', $campusId)
            ->whereNotNull('folio')
            ->whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->orderBy('payment_date', 'desc')
            ->orderBy('folio', 'desc')
            ->first();
        
        if (!$lastTransaction) {
            return 1;
        }
        
        // Retornamos el siguiente folio
        return $lastTransaction->folio + 1;
    }
    
    /**
     * Mantiene la generación de folio_new por compatibilidad
     */
    private function generateFolioNew($campusId)
    {
        // Simplemente incrementamos el último valor
        $currentMaxFolioNew = Transaction::where('campus_id', $campusId)
            ->whereNotNull('folio_new')
            ->max('folio_new');
        
        return ($currentMaxFolioNew ?: 0) + 1;
    }
    

    public function destroy($id)
    {
        $charge = Transaction::find($id);
        $charge->delete();
        return response()->json(['message' => 'Charge deleted successfully']);
    }

    public function importFolios(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'campus_id' => 'required|exists:campuses,id'
        ]);

        $file = $request->file('file');
        $campus_id = $request->input('campus_id');
        
        if (!$file->isValid()) {
            return response()->json(['error' => 'Invalid file upload'], 400);
        }

        $path = $file->getRealPath();
        $records = array_map('str_getcsv', file($path));
        
        // Remove header row if exists
        if (isset($records[0]) && is_array($records[0]) && count($records[0]) >= 3) {
            // Check if first row looks like a header
            if (!is_numeric($records[0][0])) {
                array_shift($records);
            }
        }

        $errors = [];
        $updated = 0;
        $notFound = 0;

        try {
            DB::beginTransaction();
            
            foreach ($records as $index => $record) {
                $rowNum = $index + 1; // For error reporting
                
                // Validate record structure
                if (count($record) < 3) {
                    $errors[] = "Row {$rowNum}: Invalid format, expected 3 columns";
                    continue;
                }
                
                $oldFolio = trim($record[0]);
                $recordCampusId = trim($record[1]);
                $newFolio = trim($record[2]);
                
                // Validate data
                if (empty($oldFolio) || empty($recordCampusId) || empty($newFolio)) {
                    $errors[] = "Row {$rowNum}: Empty values not allowed";
                    continue;
                }
                
                if (!is_numeric($recordCampusId)) {
                    $errors[] = "Row {$rowNum}: Campus ID must be numeric";
                    continue;
                }
                
                // Only process records for the selected campus
                if ((int)$recordCampusId !== (int)$campus_id) {
                    $errors[] = "Row {$rowNum}: Campus ID {$recordCampusId} doesn't match selected campus {$campus_id}";
                    continue;
                }
                
                // Find and update the transaction
                $transaction = Transaction::where('folio', $oldFolio)
                    ->where('campus_id', $campus_id)
                    ->first();
                
                if (!$transaction) {
                    $notFound++;
                    $errors[] = "Row {$rowNum}: Transaction with folio {$oldFolio} not found in campus {$campus_id}";
                    continue;
                }
                
                $transaction->folio = $newFolio;
                $transaction->save();
                $updated++;
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Import completed',
                'updated' => $updated,
                'not_found' => $notFound,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error processing CSV file',
                'error' => $e->getMessage(),
                'errors' => $errors
            ], 500);
        }
    }
}
