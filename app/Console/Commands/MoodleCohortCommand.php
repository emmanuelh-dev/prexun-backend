<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Moodle\MoodleService;

class MoodleCohortCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'moodle:cohort 
                            {action : La acción a realizar (remove-user, remove-users, remove-all, add-users, list-user-cohorts)}
                            {--username= : Username del usuario}
                            {--user-id= : ID del usuario en Moodle}
                            {--cohort-id= : ID del cohort}
                            {--members= : JSON con array de members [{userid:123,cohortid:456}]}';

    /**
     * The console command description.
     */
    protected $description = 'Gestionar cohorts de Moodle desde la línea de comandos';

    protected MoodleService $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        parent::__construct();
        $this->moodleService = $moodleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'remove-user':
                return $this->removeUserFromCohort();
            
            case 'remove-users':
                return $this->removeUsersFromCohorts();
            
            case 'remove-all':
                return $this->removeUserFromAllCohorts();
            
            case 'add-users':
                return $this->addUsersToCohorts();
            
            case 'list-user-cohorts':
                return $this->listUserCohorts();
            
            default:
                $this->error("Acción no válida: {$action}");
                $this->info("Acciones disponibles: remove-user, remove-users, remove-all, add-users, list-user-cohorts");
                return 1;
        }
    }

    private function removeUserFromCohort()
    {
        $userId = $this->option('user-id');
        $cohortId = $this->option('cohort-id');

        if (!$userId || !$cohortId) {
            $this->error('Se requieren --user-id y --cohort-id para esta acción');
            return 1;
        }

        $this->info("Eliminando usuario {$userId} del cohort {$cohortId}...");
        
        $result = $this->moodleService->cohorts()->removeUserFromCohort($userId, $cohortId);
        
        if ($result['status'] === 'success') {
            $this->info('✅ Usuario eliminado del cohort exitosamente');
        } else {
            $this->error('❌ Error: ' . $result['message']);
            return 1;
        }

        return 0;
    }

    private function removeUsersFromCohorts()
    {
        $membersJson = $this->option('members');

        if (!$membersJson) {
            $this->error('Se requiere --members con formato JSON para esta acción');
            $this->info('Ejemplo: --members=\'[{"userid":123,"cohortid":456},{"userid":124,"cohortid":457}]\'');
            return 1;
        }

        $members = json_decode($membersJson, true);

        if (!$members || !is_array($members)) {
            $this->error('El formato JSON de members no es válido');
            return 1;
        }

        $this->info("Eliminando " . count($members) . " usuarios de sus cohorts...");
        
        $result = $this->moodleService->cohorts()->removeUsersFromCohorts($members);
        
        if ($result['status'] === 'success') {
            $this->info('✅ Usuarios eliminados de los cohorts exitosamente');
        } else {
            $this->error('❌ Error: ' . $result['message']);
            return 1;
        }

        return 0;
    }

    private function removeUserFromAllCohorts()
    {
        $username = $this->option('username');

        if (!$username) {
            $this->error('Se requiere --username para esta acción');
            return 1;
        }

        $this->info("Eliminando usuario {$username} de todos sus cohorts...");
        
        $result = $this->moodleService->cohorts()->removeUserFromAllCohorts($username);
        
        if ($result['status'] === 'success') {
            $this->info('✅ ' . $result['message']);
            if (isset($result['cohorts_removed'])) {
                $this->info("Cohorts afectados: {$result['cohorts_removed']}");
            }
        } else {
            $this->error('❌ Error: ' . $result['message']);
            return 1;
        }

        return 0;
    }

    private function addUsersToCohorts()
    {
        $membersJson = $this->option('members');

        if (!$membersJson) {
            $this->error('Se requiere --members con formato JSON para esta acción');
            $this->info('Ejemplo: --members=\'[{"userid":123,"cohortid":456},{"userid":124,"cohortid":457}]\'');
            return 1;
        }

        $members = json_decode($membersJson, true);

        if (!$members || !is_array($members)) {
            $this->error('El formato JSON de members no es válido');
            return 1;
        }

        $this->info("Agregando " . count($members) . " usuarios a cohorts...");
        
        $result = $this->moodleService->cohorts()->addUserToCohort($members);
        
        if ($result['status'] === 'success') {
            $this->info('✅ Usuarios agregados a los cohorts exitosamente');
        } else {
            $this->error('❌ Error: ' . $result['message']);
            return 1;
        }

        return 0;
    }

    private function listUserCohorts()
    {
        $username = $this->option('username');
        $userId = $this->option('user-id');

        if (!$username && !$userId) {
            $this->error('Se requiere --username o --user-id para esta acción');
            return 1;
        }

        if ($username && !$userId) {
            $this->info("Obteniendo ID de usuario para: {$username}");
            $userResult = $this->moodleService->users()->getUserByUsername($username);
            
            if ($userResult['status'] !== 'success') {
                $this->error('❌ Usuario no encontrado: ' . $userResult['message']);
                return 1;
            }
            
            $userId = $userResult['data']['id'];
            $this->info("ID de usuario encontrado: {$userId}");
        }

        $this->info("Obteniendo cohorts del usuario {$userId}...");
        
        $result = $this->moodleService->cohorts()->getUserCohorts($userId);
        
        if ($result['status'] === 'success') {
            $cohorts = $result['data']['cohorts'] ?? [];
            
            if (empty($cohorts)) {
                $this->info('ℹ️ El usuario no pertenece a ningún cohort');
            } else {
                $this->info('✅ Cohorts encontrados: ' . count($cohorts));
                
                $headers = ['ID', 'Nombre', 'Descripción'];
                $rows = [];
                
                foreach ($cohorts as $cohort) {
                    $rows[] = [
                        $cohort['id'],
                        $cohort['name'],
                        substr($cohort['description'] ?? '', 0, 50) . (strlen($cohort['description'] ?? '') > 50 ? '...' : '')
                    ];
                }
                
                $this->table($headers, $rows);
            }
        } else {
            $this->error('❌ Error: ' . $result['message']);
            return 1;
        }

        return 0;
    }
}
