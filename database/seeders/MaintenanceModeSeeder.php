<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenanceModeSeeder extends Seeder
{
    /**
     * Inserta los settings de modo mantenimiento si no existen.
     */
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'maintenance_mode',
                'label'       => 'Modo Mantenimiento',
                'value'       => 'false',
                'type'        => 'boolean',
                'description' => 'Activa un banner de aviso en el sistema para todos los usuarios.',
                'options'     => null,
                'group'       => 'sistema',
                'sort_order'  => 1,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'maintenance_message',
                'label'       => 'Mensaje de Mantenimiento',
                'value'       => 'El sistema se encuentra en mantenimiento. Algunos servicios pueden estar limitados.',
                'type'        => 'textarea',
                'description' => 'Mensaje que se muestra en el banner de modo mantenimiento.',
                'options'     => null,
                'group'       => 'sistema',
                'sort_order'  => 2,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
