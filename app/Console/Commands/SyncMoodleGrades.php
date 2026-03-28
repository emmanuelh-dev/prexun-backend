<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StudentAssignment;
use App\Services\StudentGradesService;
use Illuminate\Support\Facades\Log;

class SyncMoodleGrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grades:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza las calificaciones de los estudiantes activos con Moodle de forma automatizada.';

    /**
     * Execute the console command.
     */
    public function handle(StudentGradesService $gradesService)
    {
        $this->info('Iniciando sincronización de calificaciones con Moodle...');
        Log::info('Iniciando proceso Automático de Sincronización de Calificaciones (Grades Sync Cron)');

        $assignments = StudentAssignment::where('is_active', true)
            ->whereNotNull('period_id')
            ->get();

        if ($assignments->isEmpty()) {
            $this->info('No hay estudiantes activos para sincronizar.');
            return;
        }

        $groupedByPeriod = $assignments->groupBy('period_id');
        
        foreach ($groupedByPeriod as $periodId => $periodAssignments) {
            $studentIds = $periodAssignments->pluck('student_id')->unique()->values()->toArray();
            $total = count($studentIds);
            
            $this->info("Sincronizando Período ID {$periodId} - {$total} estudiantes encontrados.");
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach (array_chunk($studentIds, 50) as $chunk) {
                try {
                    $gradesService->getBatchGrades($chunk, $periodId, true);
                    $bar->advance(count($chunk));
                } catch (\Exception $e) {
                    Log::error("Error sincronizando chunk de periodo {$periodId}", ['error' => $e->getMessage()]);
                }
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info('Sincronización finalizada correctamente.');
        Log::info('Finalizó proceso Automático de Sincronización de Calificaciones (Grades Sync Cron)');
    }
}
