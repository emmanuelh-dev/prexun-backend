<?php

// Script de prueba para ver cómo se ven los gastos agrupados
// Ejecutar con: php test_gastos_grouped.php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Gasto;
use App\Http\Controllers\Api\GastoController;
use Illuminate\Http\Request;

echo "=== Vista Previa de Gastos Agrupados ===\n\n";

$campusId = 2;

$gastos = Gasto::with('admin', 'user')
    ->where('campus_id', $campusId)
    ->orderByRaw('YEAR(date) DESC, MONTH(date) DESC')
    ->orderBy('folio', 'asc')
    ->get();

$grouped = $gastos->groupBy(function ($gasto) {
    return \Carbon\Carbon::parse($gasto->date)->format('Y-m');
})->map(function ($monthGastos, $yearMonth) {
    list($year, $month) = explode('-', $yearMonth);
    $monthName = \Carbon\Carbon::create($year, $month)->locale('es')->translatedFormat('F Y');
    
    return [
        'year_month' => $yearMonth,
        'month_label' => $monthName,
        'total_gastos' => $monthGastos->count(),
        'total_amount' => $monthGastos->sum('amount'),
        'gastos' => $monthGastos->map(function($g) {
            $displayFolio = ($g->folio_prefix ?? '') . ($g->folio ?? '');
            return [
                'id' => $g->id,
                'folio' => $g->folio,
                'display_folio' => $displayFolio,
                'concept' => $g->concept,
                'amount' => $g->amount,
                'date' => $g->date,
                'category' => $g->category,
            ];
        })->values()
    ];
})->values();

foreach ($grouped as $group) {
    echo "📅 {$group['month_label']}\n";
    echo "   Total: {$group['total_gastos']} gastos | Monto total: $" . number_format($group['total_amount'], 2) . "\n";
    echo "   ───────────────────────────────────────────────\n";
    
    foreach ($group['gastos'] as $gasto) {
        $folio = str_pad($gasto['display_folio'], 15);
        $amount = '$' . str_pad(number_format($gasto['amount'], 2), 10, ' ', STR_PAD_LEFT);
        echo "   {$folio} | {$amount} | {$gasto['concept']}\n";
    }
    echo "\n";
}

echo "✅ Agrupación completada!\n";
