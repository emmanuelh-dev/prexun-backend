<?php

/**
 * Script de prueba para los reportes de asistencia
 * Ejecutar con: php test_attendance_reports.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Student;
use App\Models\Attendance;
use App\Models\Grupo;
use Carbon\Carbon;

// Configurar la aplicación Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== PRUEBA DE REPORTES DE ASISTENCIA ===\n\n";

// 1. Obtener un estudiante de prueba
$student = Student::with('grupo')->first();
if (!$student) {
    echo "❌ No se encontraron estudiantes en la base de datos\n";
    exit(1);
}

echo "✅ Estudiante de prueba encontrado:\n";
echo "   - ID: {$student->id}\n";
echo "   - Nombre: {$student->firstname} {$student->lastname}\n";
echo "   - Matrícula: {$student->matricula}\n";
echo "   - Grupo: " . ($student->grupo ? $student->grupo->name : 'Sin grupo') . "\n\n";

// 2. Crear algunas asistencias de prueba
$startDate = Carbon::now()->subDays(10);
$endDate = Carbon::now();

echo "📅 Generando asistencias de prueba del {$startDate->format('Y-m-d')} al {$endDate->format('Y-m-d')}...\n";

$testAttendances = [];
$currentDate = $startDate->copy();

while ($currentDate <= $endDate) {
    // Simular que el estudiante va 80% del tiempo
    $isPresent = rand(1, 100) <= 80;
    
    if ($isPresent && $student->grupo) {
        $attendance = Attendance::updateOrCreate(
            [
                'student_id' => $student->id,
                'grupo_id' => $student->grupo->id,
                'date' => $currentDate->format('Y-m-d'),
            ],
            [
                'present' => true,
                'attendance_time' => $currentDate->setTime(rand(8, 10), rand(0, 59))->toISOString(),
            ]
        );
        
        $testAttendances[] = $attendance;
        echo "   ✅ {$currentDate->format('Y-m-d')} - PRESENTE\n";
    } else {
        echo "   ❌ {$currentDate->format('Y-m-d')} - AUSENTE (no registrado)\n";
    }
    
    $currentDate->addDay();
}

echo "\n📊 Asistencias de prueba creadas: " . count($testAttendances) . "\n\n";

// 3. Probar el endpoint de reporte individual
echo "🔍 PROBANDO REPORTE INDIVIDUAL...\n";

try {
    $controller = new \App\Http\Controllers\Api\TeacherAttendanceController();
    
    $request = new \Illuminate\Http\Request([
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d'),
        'exclude_weekends' => true,
    ]);
    
    $response = $controller->getStudentAttendanceReport($student->id, $request);
    $reportData = json_decode($response->getContent(), true);
    
    if ($reportData['success']) {
        $stats = $reportData['data']['statistics'];
        echo "✅ Reporte generado exitosamente:\n";
        echo "   - Total días: {$stats['total_days']}\n";
        echo "   - Días presente: {$stats['present_count']}\n";
        echo "   - Días ausente: {$stats['absent_count']}\n";
        echo "   - Porcentaje asistencia: {$stats['attendance_percentage']}%\n";
        echo "   - Porcentaje ausencias: {$stats['absent_percentage']}%\n\n";
        
        // Mostrar algunos días de ejemplo
        echo "📋 Primeros 5 días del reporte:\n";
        $allDays = $reportData['data']['attendance_details']['all_days'];
        for ($i = 0; $i < min(5, count($allDays)); $i++) {
            $day = $allDays[$i];
            $status = $day['status'] === 'present' ? '✅ PRESENTE' : '❌ AUSENTE';
            echo "   - {$day['date']} ({$day['day_name_es']}): {$status}\n";
        }
    } else {
        echo "❌ Error en el reporte: {$reportData['message']}\n";
    }
} catch (Exception $e) {
    echo "❌ Excepción al generar reporte: {$e->getMessage()}\n";
}

echo "\n";

// 4. Probar el endpoint de reporte de grupo (si el estudiante tiene grupo)
if ($student->grupo) {
    echo "👥 PROBANDO REPORTE DE GRUPO...\n";
    
    try {
        $groupRequest = new \Illuminate\Http\Request([
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'exclude_weekends' => true,
        ]);
        
        $groupResponse = $controller->getGroupAttendanceReport($student->grupo->id, $groupRequest);
        $groupReportData = json_decode($groupResponse->getContent(), true);
        
        if ($groupReportData['success']) {
            $groupStats = $groupReportData['data']['group_statistics'];
            echo "✅ Reporte de grupo generado exitosamente:\n";
            echo "   - Grupo: {$groupReportData['data']['group']['name']}\n";
            echo "   - Total estudiantes: {$groupStats['total_students']}\n";
            echo "   - Total días posibles: {$groupStats['total_possible_days']}\n";
            echo "   - Total presencias: {$groupStats['total_present_days']}\n";
            echo "   - Total ausencias: {$groupStats['total_absent_days']}\n";
            echo "   - Porcentaje grupal: {$groupStats['group_attendance_percentage']}%\n\n";
        } else {
            echo "❌ Error en el reporte de grupo: {$groupReportData['message']}\n";
        }
    } catch (Exception $e) {
        echo "❌ Excepción al generar reporte de grupo: {$e->getMessage()}\n";
    }
}

echo "🎯 EJEMPLOS DE USO VÍA API:\n\n";

echo "📡 Para obtener reporte individual:\n";
echo "GET /api/teacher/attendance/student/{$student->id}/report\n";
echo "Parámetros: start_date={$startDate->format('Y-m-d')}&end_date={$endDate->format('Y-m-d')}&exclude_weekends=true\n\n";

if ($student->grupo) {
    echo "📡 Para obtener reporte de grupo:\n";
    echo "GET /api/teacher/attendance/group/{$student->grupo->id}/report\n";
    echo "Parámetros: start_date={$startDate->format('Y-m-d')}&end_date={$endDate->format('Y-m-d')}&exclude_weekends=true\n\n";
}

echo "🔧 CARACTERÍSTICAS DEL SISTEMA:\n";
echo "✅ Calcula automáticamente los días ausentes (sin registros)\n";
echo "✅ Excluye fines de semana opcionalmente\n";
echo "✅ Proporciona estadísticas detalladas\n";
echo "✅ Incluye reportes individuales y grupales\n";
echo "✅ Muestra detalles día por día\n";
echo "✅ Calcula porcentajes de asistencia\n\n";

echo "=== PRUEBA COMPLETADA ===\n";
