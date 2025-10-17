<?php

// Script de prueba para verificar folios con diferentes fechas
// Ejecutar con: php test_gasto_folios_dates.php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Gasto;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;

echo "=== Test de Folios por Fecha ===\n\n";

$campus = Campus::find(2); // Campus ID 2
if (!$campus) {
    echo "❌ Campus ID 2 no encontrado\n";
    exit(1);
}

echo "Campus: {$campus->name} (ID: {$campus->id})\n";
echo "Letra del campus: " . strtoupper(substr($campus->name, 0, 1)) . "\n\n";

// Verificar gastos por mes
$meses = [
    '2025-08' => 'Agosto 2025',
    '2025-09' => 'Septiembre 2025',
    '2025-10' => 'Octubre 2025',
];

foreach ($meses as $yearMonth => $label) {
    list($year, $month) = explode('-', $yearMonth);
    
    $gastos = Gasto::where('campus_id', $campus->id)
        ->whereYear('date', $year)
        ->whereMonth('date', $month)
        ->orderBy('folio', 'asc')
        ->get(['id', 'concept', 'date', 'folio', 'folio_prefix']);
    
    echo "📅 {$label}: " . $gastos->count() . " gastos\n";
    
    if ($gastos->count() > 0) {
        foreach ($gastos as $g) {
            $displayFolio = ($g->folio_prefix ?? '') . ($g->folio ?? '');
            echo "   - Folio {$displayFolio} | Concepto: {$g->concept} | Fecha: {$g->date}\n";
        }
    } else {
        echo "   (Sin gastos)\n";
    }
    echo "\n";
}

echo "✅ Revisión completa!\n\n";
echo "💡 Observa que:\n";
echo "   - Cada mes tiene su propia secuencia de folios (1, 2, 3...)\n";
echo "   - El prefijo cambia según el mes (0825, 0925, 1025)\n";
echo "   - Los folios se reinician cada mes\n";
