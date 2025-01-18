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
        // Conteos básicos
        $campusesCount = Campus::count();
        $usersCount = User::count();
        $studentsCount = Student::count();
        
        // Configuración de fechas
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date')) 
            : Carbon::now()->startOfMonth();
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date')) 
            : Carbon::now();

        // Transacciones pagadas
        $transactions = Transaction::where('paid', true)
            ->whereBetween('payment_date', [$startDate, $endDate]);

        $transactionsCount = $transactions->count();
        $totalAmount = $transactions->sum('amount');

        // Transacciones pendientes
        $pendingTransactions = Transaction::where('paid', false)
            ->whereBetween('updated_at', [$startDate, $endDate]);
        
        $pendingCount = $pendingTransactions->count();
        $pendingAmount = $pendingTransactions->sum('amount');

        // Transacciones diarias (pagadas)
        $dailyTransactions = Transaction::where('paid', true)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Transacciones mensuales (pagadas)
        $monthlyTransactions = Transaction::where('paid', true)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(updated_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

      // En DashboardController.php

// Gastos diarios
$dailyGastos = Gasto::whereBetween('created_at', [$startDate, $endDate])
->select(
    DB::raw('DATE(created_at) as date'),
    DB::raw('COUNT(*) as count'),
    DB::raw('SUM(amount) as total')
)
->groupBy(DB::raw('DATE(created_at)')) // Cambiado aquí
->orderBy('date')
->get();

// Gastos mensuales
$monthlyGastos = Gasto::whereBetween('created_at', [$startDate, $endDate])
->select(
    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
    DB::raw('COUNT(*) as count'),
    DB::raw('SUM(amount) as total')
)
->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")')) // Cambiado aquí
->orderBy('month')
->get();


        // Totales de gastos
        $totalGastos = Gasto::whereBetween('created_at', [$startDate, $endDate]);
        $gastosCount = $totalGastos->count();
        $gastosTotalAmount = $totalGastos->sum('amount');

        // Balance (Ingresos - Gastos)
        $balance = $totalAmount - $gastosTotalAmount;

        return response()->json([
            'summary' => [
                'campuses' => $campusesCount,
                'users' => $usersCount,
                'students' => $studentsCount,
                'gastos' => [
                    'count' => $gastosCount,
                    'amount' => $gastosTotalAmount
                ],
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
                ],
                'balance' => $balance
            ],
            'chartData' => [
                'transactions' => [
                    'daily' => $dailyTransactions,
                    'monthly' => $monthlyTransactions
                ],
                'gastos' => [
                    'daily' => $dailyGastos,
                    'monthly' => $monthlyGastos
                ]
            ],
            'dateRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }
}
