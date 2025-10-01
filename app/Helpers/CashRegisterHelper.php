<?php

namespace App\Helpers;

use App\Models\CashRegister;
use Illuminate\Support\Facades\Log;

class CashRegisterHelper
{
    /**
     * Obtiene la caja activa para un campus específico
     * 
     * @param int $campusId
     * @return CashRegister|null
     */
    public static function getActiveCashRegister($campusId)
    {
        return CashRegister::getActiveByCampus($campusId);
    }

    /**
     * Asigna automáticamente la caja activa a una transacción o gasto
     * si el método de pago es efectivo y no se especificó una caja
     * 
     * @param array $data
     * @param string $paymentMethodKey - Puede ser 'payment_method' o 'method'
     * @return array
     */
    public static function autoAssignCashRegister(array $data, string $paymentMethodKey = 'payment_method')
    {
        // Solo asignar si el método de pago es efectivo y no se especificó una caja
        if (!isset($data['cash_register_id']) || $data['cash_register_id'] === null) {
            $paymentMethod = $data[$paymentMethodKey] ?? null;
            
            // Normalizar método de pago
            $isCashPayment = in_array(strtolower($paymentMethod), ['cash', 'efectivo']);
            
            if ($isCashPayment && isset($data['campus_id'])) {
                $activeCashRegister = self::getActiveCashRegister($data['campus_id']);
                
                if ($activeCashRegister) {
                    $data['cash_register_id'] = $activeCashRegister->id;
                    Log::info("Auto-asignando caja {$activeCashRegister->id} al campus {$data['campus_id']}");
                } else {
                    Log::warning("No hay caja abierta para el campus {$data['campus_id']} en pago en efectivo");
                }
            }
        }
        
        return $data;
    }

    /**
     * Normaliza el método de pago para que sea consistente
     * 
     * @param string $method
     * @return string
     */
    public static function normalizePaymentMethod($method)
    {
        $method = strtolower(trim($method));
        
        $normalizationMap = [
            'efectivo' => 'cash',
            'transferencia' => 'transfer',
            'tarjeta' => 'card',
        ];
        
        return $normalizationMap[$method] ?? $method;
    }

    /**
     * Valida que haya una caja abierta para realizar un pago en efectivo
     * 
     * @param int $campusId
     * @param string $paymentMethod
     * @return bool
     */
    public static function validateCashRegisterForCashPayment($campusId, $paymentMethod)
    {
        $isCashPayment = in_array(strtolower($paymentMethod), ['cash', 'efectivo']);
        
        if (!$isCashPayment) {
            return true; // No es necesario validar si no es efectivo
        }
        
        $activeCashRegister = self::getActiveCashRegister($campusId);
        
        return $activeCashRegister !== null;
    }
}
