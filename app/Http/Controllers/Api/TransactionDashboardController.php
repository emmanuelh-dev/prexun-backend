<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Gasto;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionDashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'campus_id' => 'nullable|exists:campuses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'payment_method' => 'nullable|in:cash,card,transfer',
            'transaction_type' => 'nullable|in:income,expense,all'
        ]);

        $startDate = $validated['start_date'] ? Carbon::parse($validated['start_date']) : Carbon::now()->startOfMonth();
        $endDate = $validated['end_date'] ? Carbon::parse($validated['end_date']) : Carbon::now()->endOfMonth();
        $campusId = $validated['campus_id'] ?? null;
        $paymentMethod = $validated['payment_method'] ?? null;
        $transactionType = $validated['transaction_type'] ?? 'all';

        // Base query for transactions (income)
        $transactionsQuery = Transaction::with(['student', 'campus', 'card'])
            ->where('paid', true)
            ->whereBetween('payment_date', [$startDate, $endDate]);

        // Base query for expenses
        $gastosQuery = Gasto::with(['admin', 'user', 'campus'])
            ->whereBetween('date', [$startDate, $endDate]);

        // Apply campus filter
        if ($campusId) {
            $transactionsQuery->where('campus_id', $campusId);
            $gastosQuery->where('campus_id', $campusId);
        }

        // Apply payment method filter
        if ($paymentMethod) {
            $transactionsQuery->where('payment_method', $paymentMethod);
            $gastosQuery->where('method', $paymentMethod);
        }

        $transactions = collect();
        $gastos = collect();

        // Get data based on transaction type
        if ($transactionType === 'income' || $transactionType === 'all') {
            $transactions = $transactionsQuery->get();
        }

        if ($transactionType === 'expense' || $transactionType === 'all') {
            $gastos = $gastosQuery->get();
        }

        // Calculate totals by payment method
        $paymentMethodTotals = $this->calculatePaymentMethodTotals($transactions, $gastos);

        // Calculate daily summaries
        $dailySummary = $this->calculateDailySummary($transactions, $gastos, $startDate, $endDate);

        // Get campus summary
        $campusSummary = $this->calculateCampusSummary($transactions, $gastos);

        // Prepare transaction list for table
        $transactionList = $this->prepareTransactionList($transactions, $gastos);

        return response()->json([
            'summary' => [
                'total_income' => $transactions->sum('amount'),
                'total_expenses' => $gastos->sum('amount'),
                'net_balance' => $transactions->sum('amount') - $gastos->sum('amount'),
                'transaction_count' => $transactions->count(),
                'expense_count' => $gastos->count(),
                'total_count' => $transactions->count() + $gastos->count()
            ],
            'payment_method_totals' => $paymentMethodTotals,
            'daily_summary' => $dailySummary,
            'campus_summary' => $campusSummary,
            'transactions' => $transactionList,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'campus_id' => $campusId,
                'payment_method' => $paymentMethod,
                'transaction_type' => $transactionType
            ]
        ]);
    }

    private function calculatePaymentMethodTotals($transactions, $gastos)
    {
        $methods = ['cash', 'card', 'transfer'];
        $totals = [];

        foreach ($methods as $method) {
            $incomeTotal = $transactions->where('payment_method', $method)->sum('amount');
            $expenseTotal = $gastos->where('method', $method)->sum('amount');
            
            $totals[$method] = [
                'income' => $incomeTotal,
                'expenses' => $expenseTotal,
                'net' => $incomeTotal - $expenseTotal,
                'income_count' => $transactions->where('payment_method', $method)->count(),
                'expense_count' => $gastos->where('method', $method)->count()
            ];
        }

        return $totals;
    }

    private function calculateDailySummary($transactions, $gastos, $startDate, $endDate)
    {
        $dailyData = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            
            $dayTransactions = $transactions->filter(function ($transaction) use ($current) {
                return Carbon::parse($transaction->payment_date)->format('Y-m-d') === $current->format('Y-m-d');
            });
            
            $dayGastos = $gastos->filter(function ($gasto) use ($current) {
                return Carbon::parse($gasto->date)->format('Y-m-d') === $current->format('Y-m-d');
            });

            $dailyData[] = [
                'date' => $dateStr,
                'income' => $dayTransactions->sum('amount'),
                'expenses' => $dayGastos->sum('amount'),
                'net' => $dayTransactions->sum('amount') - $dayGastos->sum('amount'),
                'transaction_count' => $dayTransactions->count(),
                'expense_count' => $dayGastos->count()
            ];

            $current->addDay();
        }

        return $dailyData;
    }

    private function calculateCampusSummary($transactions, $gastos)
    {
        $campusData = [];
        
        // Group transactions by campus
        $transactionsByCampus = $transactions->groupBy('campus_id');
        $gastosByCampus = $gastos->groupBy('campus_id');
        
        $allCampusIds = $transactionsByCampus->keys()->merge($gastosByCampus->keys())->unique();
        
        foreach ($allCampusIds as $campusId) {
            $campusTransactions = $transactionsByCampus->get($campusId, collect());
            $campusGastos = $gastosByCampus->get($campusId, collect());
            $campus = $campusTransactions->first()?->campus ?? $campusGastos->first()?->campus;
            
            $campusData[] = [
                'campus_id' => $campusId,
                'campus_name' => $campus?->name ?? 'Campus no encontrado',
                'income' => $campusTransactions->sum('amount'),
                'expenses' => $campusGastos->sum('amount'),
                'net' => $campusTransactions->sum('amount') - $campusGastos->sum('amount'),
                'transaction_count' => $campusTransactions->count(),
                'expense_count' => $campusGastos->count()
            ];
        }
        
        return collect($campusData)->sortByDesc('net')->values()->all();
    }

    private function prepareTransactionList($transactions, $gastos)
    {
        $list = [];
        
        // Add transactions (income)
        foreach ($transactions as $transaction) {
            $list[] = [
                'id' => $transaction->id,
                'type' => 'income',
                'amount' => $transaction->amount,
                'payment_method' => $transaction->payment_method,
                'date' => $transaction->payment_date,
                'description' => 'Pago de ' . ($transaction->student?->firstname ?? 'Estudiante') . ' ' . ($transaction->student?->lastname ?? ''),
                'campus' => $transaction->campus?->name,
                'reference' => $transaction->folio ?? $transaction->uuid,
                'student_name' => ($transaction->student?->firstname ?? '') . ' ' . ($transaction->student?->lastname ?? ''),
                'notes' => $transaction->notes
            ];
        }
        
        // Add expenses
        foreach ($gastos as $gasto) {
            $list[] = [
                'id' => $gasto->id,
                'type' => 'expense',
                'amount' => $gasto->amount,
                'payment_method' => $gasto->method,
                'date' => $gasto->date,
                'description' => $gasto->concept,
                'campus' => $gasto->campus?->name,
                'reference' => null,
                'student_name' => null,
                'notes' => null,
                'category' => $gasto->category,
                'user_name' => $gasto->user?->name
            ];
        }
        
        // Sort by date descending
        return collect($list)->sortByDesc('date')->values()->all();
    }

    public function getCampuses()
    {
        $campuses = Campus::select('id', 'name')->get();
        return response()->json($campuses);
    }
}