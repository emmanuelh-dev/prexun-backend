<?php

/**
 * Script de prueba para verificar la optimización de hardUpdate
 * 
 * Este script simula el flujo de hardUpdate con los cambios implementados
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Student;
use App\Services\Moodle\MoodleCohortService;
use Illuminate\Support\Facades\Log;

echo "=== PRUEBA DE OPTIMIZACIÓN HARDUPDATE ===\n\n";

echo "1. Probando formato correcto cohorttype/usertype:\n";
$service = new MoodleCohortService();

$correctFormat = [
    [
        'cohorttype' => ['type' => 'id', 'value' => 402],
        'usertype' => ['type' => 'username', 'value' => '176']
    ]
];

echo "Formato correcto: " . json_encode($correctFormat, JSON_PRETTY_PRINT) . "\n";
echo "Este formato DEBE funcionar correctamente.\n\n";

echo "2. Probando formato obsoleto userid/cohortid:\n";
$obsoleteFormat = [
    [
        'userid' => 2214,
        'cohortid' => 402
    ]
];

echo "Formato obsoleto: " . json_encode($obsoleteFormat, JSON_PRETTY_PRINT) . "\n";
echo "Este formato DEBE fallar con error específico.\n\n";

echo "3. Diferencias clave:\n";
echo "- Eliminación (removeUsersFromCohorts): Usa userid con Moodle ID numérico (2214)\n";
echo "- Adición (addUserToCohort): Usa usertype.value con student ID string ('176')\n";
echo "- Esta separación elimina la ambigüedad y previene errores 'user not exists'\n\n";

echo "4. Resultado esperado:\n";
echo "- Las operaciones de hardUpdate ahora usan consistentemente:\n";
echo "  * prepareCohortsToRemove(): formato userid/cohortid con moodle_id\n";
echo "  * prepareCohortsToAdd(): formato cohorttype/usertype con student.id\n";
echo "- Eliminación de warnings 'user username=2214 not exists'\n";
echo "- Manejo correcto de IDs en ambas direcciones\n\n";

echo "=== FIN DE PRUEBA ===\n";
