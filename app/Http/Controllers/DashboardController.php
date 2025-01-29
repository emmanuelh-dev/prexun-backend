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
    // Función auxiliar para generar array de días
    private function generateDailyArray($startDate, $endDate, $data) {
        $dates = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayData = $data->firstWhere('date', $dateStr);
            
            $dates[] = [
                'date' => $dateStr,
                'count' => $dayData ? $dayData->count : 0,
                'total' => $dayData ? $dayData->total : 0
            ];

            $currentDate->addDay();
        }

        return $dates;
    }

    // Función auxiliar para generar array de meses
    private function generateMonthlyArray($startDate, $endDate, $data) {
        $months = [];
        $currentDate = Carbon::parse($startDate)->startOfYear(); // Comenzar desde enero
        $lastDate = Carbon::parse($endDate)->endOfYear(); // Terminar en diciembre

        while ($currentDate <= $lastDate) {
            $monthStr = $currentDate->format('Y-m');
            $monthData = $data->firstWhere('month', $monthStr);
            
            $months[] = [
                'month' => $monthStr,
                'count' => $monthData ? $monthData->count : 0,
                'total' => $monthData ? $monthData->total : 0
            ];

            $currentDate->addMonth();
        }

        return $months;
    }

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
        $dailyTransactionsQuery = Transaction::where('paid', true)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyTransactions = $this->generateDailyArray($startDate, $endDate, $dailyTransactionsQuery);

        // Transacciones mensuales (pagadas)
        $monthlyTransactionsQuery = Transaction::where('paid', true)
            ->whereBetween('updated_at', [$startDate->startOfYear(), $endDate->endOfYear()])
            ->select(
                DB::raw('DATE_FORMAT(updated_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyTransactions = $this->generateMonthlyArray($startDate, $endDate, $monthlyTransactionsQuery);

        // Gastos diarios
        $dailyGastosQuery = Gasto::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $dailyGastos = $this->generateDailyArray($startDate, $endDate, $dailyGastosQuery);

        // Gastos mensuales
        $monthlyGastosQuery = Gasto::whereBetween('created_at', [$startDate->startOfYear(), $endDate->endOfYear()])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
            ->orderBy('month')
            ->get();

        $monthlyGastos = $this->generateMonthlyArray($startDate, $endDate, $monthlyGastosQuery);

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
