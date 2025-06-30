<?php

namespace App\Helpers;

use App\Models\SiteSetting;
use App\Models\Period;

class SiteConfigHelper
{
    /**
     * Obtener el período por defecto
     */
    public static function getDefaultPeriod()
    {
        $periodId = SiteSetting::getValue('default_period_id');
        
        if ($periodId) {
            return Period::find($periodId);
        }
        
        // Fallback: obtener el período más reciente
        return Period::orderBy('created_at', 'desc')->first();
    }

    /**
     * Obtener el ID del período por defecto
     */
    public static function getDefaultPeriodId()
    {
        $period = self::getDefaultPeriod();
        return $period ? $period->id : null;
    }

    /**
     * Obtener configuraciones para la interfaz
     */
    public static function getUISettings()
    {
        return [
            'default_items_per_page' => (int) SiteSetting::getValue('default_items_per_page', 10),
            'default_theme' => SiteSetting::getValue('default_theme', 'light'),
            'default_period_id' => self::getDefaultPeriodId(),
        ];
    }

    /**
     * Obtener configuraciones de pagos
     */
    public static function getPaymentSettings()
    {
        $enabledMethods = SiteSetting::getValue('payment_methods_enabled', ['cash']);
        
        // Si está almacenado como JSON string, parsearlo
        if (is_string($enabledMethods)) {
            $enabledMethods = json_decode($enabledMethods, true) ?: ['cash'];
        }
        
        return [
            'enabled_methods' => $enabledMethods,
            'default_method' => SiteSetting::getValue('default_payment_method', 'cash'),
            'require_proof' => filter_var(SiteSetting::getValue('require_payment_proof', true), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Verificar si un método de pago está habilitado
     */
    public static function isPaymentMethodEnabled($method)
    {
        $settings = self::getPaymentSettings();
        return in_array($method, $settings['enabled_methods']);
    }
}
