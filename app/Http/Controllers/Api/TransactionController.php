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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class TransactionController extends Controller
{

  public function index(Request $request)
  {
    $campus_id = $request->campus_id;
    $perPage = (int) $request->query('per_page', 10);
    $page = (int) $request->query('page', 1);
    $search = $request->query('search');
    $payment_method = $request->query('payment_method');
    $card_id = $request->query('card_id');

    $query = Transaction::with(['student', 'campus', 'student.grupo', 'card'])
      ->where('campus_id', $campus_id)
      ->where('paid', true);

    // Filtro de búsqueda por nombre de estudiante
    if ($search) {
      $query->whereHas('student', function ($q) use ($search) {
        $searchTerms = explode(' ', trim($search));
        foreach ($searchTerms as $term) {
          $q->where(function ($subQuery) use ($term) {
            $subQuery->where('firstname', 'LIKE', '%' . $term . '%')
              ->orWhere('lastname', 'LIKE', '%' . $term . '%')
              ->orWhere('username', 'LIKE', '%' . $term . '%');
          });
        }
      });
    }

    // Filtro por método de pago
    if ($payment_method && $payment_method !== 'all') {
      $query->where('payment_method', $payment_method);
    }

    // Filtro por tarjeta específica
    if ($card_id && $card_id !== 'all') {
      $query->where('card_id', $card_id);
    }

    $charges = $query->orderBy('payment_date', 'desc')
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
    $search = $request->query('search');
    $payment_method = $request->query('payment_method');
    $card_id = $request->query('card_id');

    if (!$campus_id) {
      return response()->json(['error' => 'campus_id is required'], 400);
    }

    $query = Transaction::with('student', 'campus', 'student.grupo', 'card')
      ->where('campus_id', $campus_id)
      ->where('paid', false);

    // Filtro de búsqueda por nombre de estudiante
    if ($search) {
      $query->whereHas('student', function ($q) use ($search) {
        $searchTerms = explode(' ', trim($search));
        foreach ($searchTerms as $term) {
          $q->where(function ($subQuery) use ($term) {
            $subQuery->where('firstname', 'LIKE', '%' . $term . '%')
              ->orWhere('lastname', 'LIKE', '%' . $term . '%')
              ->orWhere('username', 'LIKE', '%' . $term . '%');
          });
        }
      });
    }

    // Filtro por método de pago
    if ($payment_method && $payment_method !== 'all') {
      $query->where('payment_method', $payment_method);
    }

    // Filtro por tarjeta específica
    if ($card_id && $card_id !== 'all') {
      $query->where('card_id', $card_id);
    }

    if ($expiration_date) {
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
        return response()->json(['error' => 'Invalid date format (YYYY-MM-DD)'], 400);
      }
      $query->whereDate('expiration_date', $expiration_date);
    }

    $charges = $query->orderBy('expiration_date', 'asc')
      ->paginate($perPage, ['*'], 'page', $page);

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
      'expiration_date' => 'nullable|date',
      'payment_date' => 'nullable|date_format:Y-m-d',
      'notes' => 'nullable|string|max:255',
      'paid' => 'required|boolean',
      'debt_id' => 'nullable|exists:debts,id',
      'image' => 'nullable|image',
      'card_id' => 'nullable|exists:cards,id',
      'sat' => 'nullable|boolean',
    ]);

    if ($request->hasFile('image')) {
      $validated['image'] = $request->file('image')->store('transactions', 'public');
    }

    try {
      return DB::transaction(function () use ($validated) {
        $shouldGenerateSpecificFolio = $this->shouldGenerateSpecificFolio($validated['payment_method'], $validated['card_id'] ?? null);

        $folio = null;
        $folioNew = null;

        // Solo generar folio si la transacción está pagada
        if ($validated['paid'] && !$shouldGenerateSpecificFolio) {
          $folio = $this->generateMonthlyFolio($validated['campus_id']);
        }
        if ($validated['paid']) {
          // Generar folio nuevo con prefijo y mes/año
          $folioNew = $this->folioNew($validated['campus_id'], $validated['payment_method'], $validated['card_id'] ?? null, $validated['payment_date'] ?? now());
        }

        $transaction = Transaction::create([
          'student_id' => $validated['student_id'],
          'campus_id' => $validated['campus_id'],
          'amount' => $validated['amount'],
          'payment_method' => $validated['payment_method'],
          'notes' => $validated['notes'] ?? null,
          'paid' => $validated['paid'],
          'transaction_type' => 'payment',
          'expiration_date' => $validated['expiration_date'] ?? Carbon::now()->addDays(15)->format('Y-m-d'),
          'uuid' => Str::uuid(),
          'debt_id' => $validated['debt_id'] ?? null,
          'card_id' => $validated['card_id'] ?? null,
          'folio' => $folio,
          'folio_new' => $folioNew,
          'image' => $validated['image'] ?? null,
          'payment_date' => $validated['payment_date'] ?? null
        ]);


        if ($validated['paid'] && $shouldGenerateSpecificFolio) {
          $paymentFolio = $this->generatePaymentMethodFolio(
            $validated['campus_id'],
            $validated['payment_method'],
            $validated['card_id'] ?? null
          );

          if ($paymentFolio) {
            $transaction->{$paymentFolio['column']} = $paymentFolio['value'];
            $transaction->save();
          }
        }

        if ($validated['debt_id'] && $validated['paid']) {
          $debt = \App\Models\Debt::find($validated['debt_id']);
          if ($debt) {
            $debt->updatePaymentStatus();
          }
        }

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
          $transaction->load('transactionDetails'),
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

  public function update($id, Request $request)
  {
    $validated = $request->validate([
      'student_id' => 'nullable|exists:students,id',
      'campus_id' => 'nullable|exists:campuses,id',
      'amount' => 'nullable|numeric|min:0',
      'payment_method' => ['nullable', Rule::in(['cash', 'transfer', 'card'])],
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
        $oldPaid = $transaction->paid;
        $oldPaymentMethod = $transaction->payment_method;
        $oldCardId = $transaction->card_id;

        $transaction->update($validated);

        $paymentMethodChanged = $oldPaymentMethod !== $transaction->payment_method;
        $cardChanged = $oldCardId !== $transaction->card_id;
        $paidStatusChanged = !$oldPaid && $transaction->paid;

        if ($transaction->paid && ($paidStatusChanged || $paymentMethodChanged || $cardChanged)) {
          $shouldGenerateSpecificFolio = $this->shouldGenerateSpecificFolio($transaction->payment_method, $transaction->card_id);

          // Si el método de pago cambió, limpiar folios específicos
          if ($paymentMethodChanged || $cardChanged) {
            $transaction->folio_transfer = null;
            $transaction->folio_cash = null;
            $transaction->folio_card = null;
          }

          if ($shouldGenerateSpecificFolio) {
            // Si cambió a método específico, limpiar folio general
            if ($paymentMethodChanged || $cardChanged) {
              $transaction->folio_new = null;
            }

            $paymentFolio = $this->generatePaymentMethodFolio(
              $transaction->campus_id,
              $transaction->payment_method,
              $transaction->card_id
            );

            if ($paymentFolio) {
              $transaction->{$paymentFolio['column']} = $paymentFolio['value'];
            }
          } else {
            // Solo generar folio general si no tiene uno ya
            if (!$transaction->folio && !$transaction->folio_new) {
              $folio = $this->generateMonthlyFolio($transaction->campus_id);
              $transaction->folio = $folio;
            }
          }

          $transaction->folio_new = $this->folioNew($transaction->campus_id, $transaction->card_id ?? null, $transaction->payment_date ?? now());
          $transaction->save();
        }

        if ($oldPaid && !$transaction->paid) {
          $transaction->folio = null;
          $transaction->folio_new = null;
          $transaction->folio_transfer = null;
          $transaction->folio_cash = null;
          $transaction->folio_card = null;
          $transaction->save();
        }

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

  private function shouldGenerateSpecificFolio($paymentMethod, $cardId = null)
  {
    if ($paymentMethod === 'cash') {
      return true;
    }

    if ($paymentMethod === 'transfer') {
      if (!$cardId) return true;

      $card = \App\Models\Card::find($cardId);
      return !($card && $card->sat);
    }

    if ($paymentMethod === 'card') {
      if (!$cardId) return false;

      $card = \App\Models\Card::find($cardId);
      return !($card && $card->sat);
    }

    return false;
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
    $transaction = Transaction::with([
      'student',
      'campus',
      'student.grupo',
      'student.assignments.period',
      'student.assignments.grupo',
      'student.assignments.semanaIntensiva',
      'card',
      'debt.assignment.period',
      'debt.assignment.grupo',
      'debt.assignment.semanaIntensiva'
    ])
      ->where('uuid', $uuid)
      ->firstOrFail();

    // Agregar el folio formateado
    $transaction->display_folio = $this->getDisplayFolio($transaction);

    return $transaction;
  }

  public function updateFolio(Request $request, $id)
  {
    $request->validate([
      'folio' => 'required|integer|min:1',
    ]);

    $transaction = Transaction::findOrFail($id);

    $transaction->folio_new = $this->folioNew($transaction->campus_id, $transaction->paymentMethod, $transaction->payment_date, $request->folio);

    $transaction->folio = $request->folio;
    $transaction->save();

    return response()->json($transaction, 200);
  }


  /**
   * Genera un folio con reinicio mensual
   */
  private function generateMonthlyFolio($campusId)
  {
    $now = now();
    $currentMonth = $now->month;
    $currentYear = $now->year;

    // Buscamos el folio más alto para este campus en el mes actual
    // independientemente de la fecha de pago
    $maxFolio = Transaction::where('campus_id', $campusId)
      ->whereNotNull('folio')
      ->where('folio', '>', 0) // Asegurar que solo tomemos valores válidos
      ->whereMonth('payment_date', $currentMonth)
      ->whereYear('payment_date', $currentYear)
      ->max(DB::raw("CAST(folio AS UNSIGNED)"));

    if (!$maxFolio) {
      return 1;
    }

    // Retornamos el siguiente folio
    return $maxFolio + 1;
  }

  /**
   * Genera folios específicos por método de pago
   */
  private function generatePaymentMethodFolio($campusId, $paymentMethod, $cardId = null)
  {
    $now = now();
    $currentMonth = $now->month;
    $currentYear = $now->year;

    // Si es transferencia y la tarjeta tiene SAT = true, usar el folio original
    if ($paymentMethod === 'transfer' && $cardId) {
      $card = \App\Models\Card::find($cardId);
      if ($card && $card->sat) {
        return null; // Mantener el folio original (folio_new)
      }
    }

    // Si es pago con tarjeta, verificar si tiene una tarjeta válida configurada
    if ($paymentMethod === 'card') {
      // Si no hay card_id, usar folio general (folio_new)
      if (!$cardId) {
        return null; // Mantener el folio original (folio_new)
      }

      // Si hay card_id, verificar si la tarjeta tiene configuración especial
      $card = \App\Models\Card::find($cardId);
      if ($card && $card->sat) {
        return null; // Mantener el folio original (folio_new) para tarjetas SAT
      }
      // Si la tarjeta no tiene SAT = true, generar folio específico T
    }

    // Determinar la columna y prefijo según el método de pago
    switch ($paymentMethod) {
      case 'transfer':
        $folioColumn = 'folio_transfer';
        $prefix = 'A';
        break;
      case 'cash':
        $folioColumn = 'folio_cash';
        $prefix = 'E';
        break;
      case 'card':
        $folioColumn = 'folio_card';
        $prefix = 'T';
        break;
      default:
        return null;
    }

    $maxFolio = Transaction::where('campus_id', $campusId)
      ->whereNotNull($folioColumn)
      ->where($folioColumn, '>', 0)
      ->whereMonth('created_at', $currentMonth)
      ->whereYear('created_at', $currentYear)
      ->max(DB::raw("CAST({$folioColumn} AS UNSIGNED)"));

    $nextFolio = ($maxFolio ?? 0) + 1;

    return [
      'column' => $folioColumn,
      'value' => $nextFolio,
      'formatted' => $prefix . str_pad($nextFolio, 4, '0', STR_PAD_LEFT)
    ];
  }

  /**
   * Obtiene el folio formateado para mostrar según el método de pago
   */
  public function getDisplayFolio($transaction)
  {
    // Obtener la letra del campus
    $campus = \App\Models\Campus::find($transaction->campus_id);
    $letraCampus = $campus ? strtoupper(substr($campus->name, 0, 1)) : '';

    // Si es transferencia y tiene SAT = true, usar folio_new
    if ($transaction->payment_method === 'transfer' && $transaction->card_id) {
      $card = \App\Models\Card::find($transaction->card_id);
      if ($card && $card->sat) {
        return $letraCampus . ($transaction->folio_new ?? '');
      }
    }

    // Si es pago con tarjeta, verificar configuración especial
    if ($transaction->payment_method === 'card') {
      // Si no hay card_id, usar folio_new
      if (!$transaction->card_id) {
        return $letraCampus . ($transaction->folio_new ?? '');
      }

      // Si hay card_id, verificar si la tarjeta tiene SAT = true
      $card = \App\Models\Card::find($transaction->card_id);
      if ($card && $card->sat) {
        return $letraCampus . ($transaction->folio_new ?? ''); // Usar folio general para tarjetas SAT
      }

      // Si la tarjeta no tiene SAT = true, usar folio específico T
      return $transaction->folio_card ? $letraCampus . 'T' . str_pad($transaction->folio_card, 4, '0', STR_PAD_LEFT) : $letraCampus . ($transaction->folio_new ?? '');
    }

    // Usar el folio específico del método de pago para otros métodos
    switch ($transaction->payment_method) {
      case 'transfer':
        return $transaction->folio_transfer ? $letraCampus . 'A' . str_pad($transaction->folio_transfer, 4, '0', STR_PAD_LEFT) : $letraCampus . ($transaction->folio_new ?? '');
      case 'cash':
        return $transaction->folio_cash ? $letraCampus . 'E' . str_pad($transaction->folio_cash, 4, '0', STR_PAD_LEFT) : $letraCampus . ($transaction->folio_new ?? '');
      default:
        return $letraCampus . ($transaction->folio_new ?? '');
    }
  }

  /**
   * Mantiene la generación de folio_new por compatibilidad
   */
  protected function folioNew($campusId, $paymentMethod, $cardId = null, $payment_date = null)
  {

    $date = $payment_date ? Carbon::parse($payment_date) : now();
    $mesAnio = $date->format('my');

    $campus = \App\Models\Campus::findOrFail($campusId);
    $letraCampus = strtoupper(substr($campus->name, 0, 1));
    $folioColumn = null;

    $card = \App\Models\Card::find($cardId);


    switch ($paymentMethod) {
      case 'transfer':
        if ($card->sat) {
          $folioColumn = 'I';
        } else {
          $folioColumn = 'A';
        }
        break;
      case 'cash':
        $folioColumn = 'E';
        break;
      case 'card':
        $folioColumn = 'I';
        break;
      default:
        return null;
    }


    $prefix = $letraCampus . $folioColumn . $mesAnio . ' | ';

    return $prefix;
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

  /**
   * Método para diagnosticar y reparar folios incorrectos
   * Solo para uso administrativo
   */
  public function repairFolios(Request $request)
  {
    $request->validate([
      'campus_id' => 'required|exists:campuses,id',
      'month' => 'nullable|integer|min:1|max:12',
      'year' => 'nullable|integer|min:2020|max:2030',
      'dry_run' => 'nullable|boolean'
    ]);

    $campusId = $request->campus_id;
    $month = $request->month ?? now()->month;
    $year = $request->year ?? now()->year;
    $dryRun = $request->dry_run ?? true;

    $report = [
      'campus_id' => $campusId,
      'month' => $month,
      'year' => $year,
      'dry_run' => $dryRun,
      'transactions_processed' => 0,
      'folios_fixed' => 0,
      'changes' => [],
      'errors' => []
    ];

    try {
      $transactions = Transaction::where('campus_id', $campusId)
        ->whereMonth('created_at', $month)
        ->whereYear('created_at', $year)
        ->where('paid', true)
        ->orderBy('created_at', 'asc')
        ->get();

      // Contadores para cada método de pago
      $folioCounters = [
        'cash' => 0,
        'transfer' => 0,
        'card' => 0
      ];

      foreach ($transactions as $transaction) {
        $report['transactions_processed']++;
        $needsUpdate = false;
        $originalTransaction = $transaction->toArray();

        // Verificar y corregir folio específico por método de pago
        if (in_array($transaction->payment_method, ['cash', 'transfer', 'card'])) {
          $folioColumn = 'folio_' . $transaction->payment_method;
          $currentFolio = $transaction->{$folioColumn};

          // Si es transferencia con tarjeta SAT, no debería tener folio específico
          if ($transaction->payment_method === 'transfer' && $transaction->card_id) {
            $card = \App\Models\Card::find($transaction->card_id);
            if ($card && $card->sat) {
              if ($currentFolio !== null) {
                $transaction->{$folioColumn} = null;
                $needsUpdate = true;
              }
              continue;
            }
          }

          // Si es pago con tarjeta, verificar configuración especial
          if ($transaction->payment_method === 'card') {
            // Si no hay card_id o la tarjeta tiene SAT = true, no debería tener folio específico
            if (!$transaction->card_id) {
              if ($currentFolio !== null) {
                $transaction->{$folioColumn} = null;
                $needsUpdate = true;
              }
              continue;
            }

            $card = \App\Models\Card::find($transaction->card_id);
            if ($card && $card->sat) {
              if ($currentFolio !== null) {
                $transaction->{$folioColumn} = null;
                $needsUpdate = true;
              }
              continue;
            }
            // Si la tarjeta no tiene SAT = true, generar folio específico T
          }

          // Incrementar contador y asignar folio correcto
          $folioCounters[$transaction->payment_method]++;
          $expectedFolio = $folioCounters[$transaction->payment_method];

          if ($currentFolio !== $expectedFolio) {
            $transaction->{$folioColumn} = $expectedFolio;
            $needsUpdate = true;
          }
        }

        if ($needsUpdate) {
          $report['folios_fixed']++;
          if (!$dryRun) {
            $transaction->save();
          }

          $report['changes'][] = [
            'transaction_id' => $transaction->id,
            'folio' => $transaction->folio,
            'payment_method' => $transaction->payment_method,
            'before' => $originalTransaction[$folioColumn] ?? null,
            'after' => $transaction->{$folioColumn}
          ];
        }
      }

      return response()->json($report);
    } catch (\Exception $e) {
      $report['errors'][] = $e->getMessage();
      return response()->json($report, 500);
    }
  }
}
