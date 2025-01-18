<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Gasto;
use App\Models\User;
use App\Models\Student;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getData(Request $request)
    {
        $campusesCount = Campus::count();
        $usersCount = User::count();
        $studentsCount = Student::count();
        $gastosCount = Gasto::count();
        
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date')) 
            : Carbon::now()->startOfMonth();
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date')) 
            : Carbon::now();

        // Filtrar solo transacciones pagadas
        $transactions = Transaction::where('paid', true)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $transactionsCount = $transactions->count();
        $totalAmount = $transactions->sum('amount');

        // También agregar estadísticas de transacciones pendientes
        $pendingTransactions = Transaction::where('paid', false)
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        $pendingCount = $pendingTransactions->count();
        $pendingAmount = $pendingTransactions->sum('amount');

        // Transacciones diarias (solo pagadas)
        $dailyTransactions = Transaction::where('paid', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Transacciones mensuales (solo pagadas)
        $monthlyTransactions = Transaction::where('paid', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'summary' => [
                'campuses' => $campusesCount,
                'users' => $usersCount,
                'students' => $studentsCount,
                'gastos' => $gastosCount,
                'transactions' => [
                    'paid' => [
                        'count' => $transactionsCount,
                        'amount' => $totalAmount
                    ],
                    'pending' => [
                        'count' => $pendingCount,
                        'amount' => $pendingAmount
                    ],
                    'total' => [
                        'count' => $transactionsCount + $pendingCount,
                        'amount' => $totalAmount + $pendingAmount
                    ]
                ]
            ],
            'chartData' => [
                'daily' => $dailyTransactions,
                'monthly' => $monthlyTransactions
            ],
            'dateRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }
}
