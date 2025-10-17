<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gasto;
use App\Models\Campus;
use App\Traits\GeneratesFolios;
use Illuminate\Support\Facades\DB;

class AssignGastoFolios extends Command
{
    use GeneratesFolios;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gastos:assign-folios 
                            {--campus_id= : ID del campus específico (opcional)}
                            {--dry-run : Simular sin guardar cambios}
                            {--all : Procesar todos los gastos, incluso los que ya tienen folio}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asignar folios a gastos que no tienen folio o regenerar todos los folios';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $campusId = $this->option('campus_id');
        $dryRun = $this->option('dry-run');
        $processAll = $this->option('all');

        $this->info('🚀 Iniciando asignación de folios para gastos...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY RUN - No se guardarán cambios');
            $this->newLine();
        }

        $query = Gasto::query();

        if ($campusId) {
            $campus = Campus::find($campusId);
            if (!$campus) {
                $this->error("❌ Campus ID {$campusId} no encontrado");
                return 1;
            }
            $query->where('campus_id', $campusId);
            $this->info("📍 Procesando campus: {$campus->name}");
        } else {
            $this->info("📍 Procesando todos los campus");
        }

        if (!$processAll) {
            $query->where(function($q) {
                $q->whereNull('folio')
                  ->orWhereNull('folio_prefix');
            });
        }

        $gastos = $query->orderBy('campus_id')
                       ->orderBy('date')
                       ->get();

        $totalGastos = $gastos->count();

        if ($totalGastos === 0) {
            $this->info('✅ No hay gastos para procesar');
            return 0;
        }

        $this->info("📊 Total de gastos a procesar: {$totalGastos}");
        $this->newLine();

        if (!$dryRun && !$this->confirm('¿Continuar con la asignación de folios?', true)) {
            $this->warn('❌ Operación cancelada');
            return 0;
        }

        $this->newLine();

        $gastosGrouped = $gastos->groupBy('campus_id');
        $totalProcessed = 0;
        $totalUpdated = 0;

        foreach ($gastosGrouped as $campusId => $campusGastos) {
            $campus = Campus::find($campusId);
            $campusName = $campus ? $campus->name : "Campus ID {$campusId}";
            
            $this->info("🏢 Procesando: {$campusName}");
            $this->line("  Total gastos: {$campusGastos->count()}");

            $folio = 1;

            foreach ($campusGastos as $gasto) {
                $totalProcessed++;
                
                $oldFolio = $gasto->folio;
                $oldPrefix = $gasto->folio_prefix;
                
                $newFolio = $folio;
                $newPrefix = $this->generateGastoFolioPrefix($campusId);

                $needsUpdate = ($oldFolio !== $newFolio) || ($oldPrefix !== $newPrefix);

                if ($needsUpdate) {
                    $totalUpdated++;
                    
                    $oldDisplay = ($oldPrefix ?? '') . ($oldFolio ?? 'null');
                    $newDisplay = $newPrefix . $newFolio;
                    
                    $this->line("    ✏️  ID {$gasto->id}: {$oldDisplay} → {$newDisplay}");

                    if (!$dryRun) {
                        $gasto->folio = $newFolio;
                        $gasto->folio_prefix = $newPrefix;
                        $gasto->save();
                    }
                }

                $folio++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Resumen:");
        $this->info("   Total procesados: {$totalProcessed}");
        $this->info("   Total actualizados: {$totalUpdated}");
        
        if ($dryRun) {
            $this->warn("   ⚠️  Cambios NO guardados (dry-run)");
        } else {
            $this->info("   ✅ Cambios guardados exitosamente");
        }
        
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return 0;
    }
}
