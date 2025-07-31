<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Template;

class TemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Bienvenida',
                'meta_id' => 'welcome_template_001',
                'is_active' => true
            ],
            [
                'name' => 'Recordatorio de Pago',
                'meta_id' => 'payment_reminder_002',
                'is_active' => true
            ],
            [
                'name' => 'Confirmación de Inscripción',
                'meta_id' => 'enrollment_confirmation_003',
                'is_active' => true
            ],
            [
                'name' => 'Información de Horarios',
                'meta_id' => 'schedule_info_004',
                'is_active' => true
            ]
        ];

        foreach ($templates as $template) {
            Template::updateOrCreate(
                ['meta_id' => $template['meta_id']],
                $template
            );
        }
    }
}
