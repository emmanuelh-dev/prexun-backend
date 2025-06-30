<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Configuraciones generales
            [
                'key' => 'site_name',
                'label' => 'Nombre del Sitio',
                'value' => 'Prexun Asesorías',
                'type' => 'text',
                'description' => 'Nombre principal del sitio web',
                'group' => 'general',
                'sort_order' => 1
            ],
            [
                'key' => 'site_description',
                'label' => 'Descripción del Sitio',
                'value' => 'Sistema de gestión educativa',
                'type' => 'textarea',
                'description' => 'Descripción principal del sitio',
                'group' => 'general',
                'sort_order' => 2
            ],
            [
                'key' => 'default_period_id',
                'label' => 'Período por Defecto',
                'value' => null, // Se seleccionará dinámicamente
                'type' => 'select',
                'description' => 'Período académico que se selecciona por defecto al cargar la aplicación',
                'group' => 'academic',
                'sort_order' => 1
            ],
            [
                'key' => 'timezone',
                'label' => 'Zona Horaria',
                'value' => 'America/Mexico_City',
                'type' => 'select',
                'description' => 'Zona horaria del sistema',
                'options' => [
                    'America/Mexico_City' => 'México Central',
                    'America/Tijuana' => 'México Pacífico',
                    'America/Cancun' => 'México Este'
                ],
                'group' => 'general',
                'sort_order' => 3
            ],
            
            // Configuraciones de pagos
            [
                'key' => 'payment_methods_enabled',
                'label' => 'Métodos de Pago Habilitados',
                'value' => json_encode(['cash', 'card', 'transfer']),
                'type' => 'json',
                'description' => 'Métodos de pago disponibles en el sistema',
                'group' => 'payments',
                'sort_order' => 1
            ],
            [
                'key' => 'default_payment_method',
                'label' => 'Método de Pago por Defecto',
                'value' => 'cash',
                'type' => 'select',
                'description' => 'Método de pago seleccionado por defecto',
                'options' => [
                    'cash' => 'Efectivo',
                    'card' => 'Tarjeta',
                    'transfer' => 'Transferencia'
                ],
                'group' => 'payments',
                'sort_order' => 2
            ],
            [
                'key' => 'require_payment_proof',
                'label' => 'Requerir Comprobante de Pago',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Si se requiere subir comprobante para ciertos métodos de pago',
                'group' => 'payments',
                'sort_order' => 3
            ],

            // Configuraciones de notificaciones
            [
                'key' => 'notifications_enabled',
                'label' => 'Notificaciones Habilitadas',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Habilitar/deshabilitar notificaciones del sistema',
                'group' => 'notifications',
                'sort_order' => 1
            ],
            [
                'key' => 'email_notifications',
                'label' => 'Notificaciones por Email',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enviar notificaciones por correo electrónico',
                'group' => 'notifications',
                'sort_order' => 2
            ],

            // Configuraciones de la interfaz
            [
                'key' => 'default_items_per_page',
                'label' => 'Elementos por Página',
                'value' => '10',
                'type' => 'select',
                'description' => 'Número de elementos mostrados por página por defecto',
                'options' => [
                    '5' => '5 elementos',
                    '10' => '10 elementos',
                    '25' => '25 elementos',
                    '50' => '50 elementos',
                    '100' => '100 elementos'
                ],
                'group' => 'interface',
                'sort_order' => 1
            ],
            [
                'key' => 'default_theme',
                'label' => 'Tema por Defecto',
                'value' => 'light',
                'type' => 'select',
                'description' => 'Tema visual por defecto del sistema',
                'options' => [
                    'light' => 'Claro',
                    'dark' => 'Oscuro',
                    'system' => 'Sistema'
                ],
                'group' => 'interface',
                'sort_order' => 2
            ],

            // Configuraciones de seguridad
            [
                'key' => 'session_timeout',
                'label' => 'Tiempo de Sesión (minutos)',
                'value' => '120',
                'type' => 'number',
                'description' => 'Tiempo en minutos antes de que expire la sesión',
                'group' => 'security',
                'sort_order' => 1
            ],
            [
                'key' => 'max_login_attempts',
                'label' => 'Intentos Máximos de Login',
                'value' => '5',
                'type' => 'number',
                'description' => 'Número máximo de intentos de login antes de bloquear cuenta',
                'group' => 'security',
                'sort_order' => 2
            ]
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
