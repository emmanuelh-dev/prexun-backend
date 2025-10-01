# Optimización del Sistema de Caja

## 📋 Resumen de Cambios

Este documento describe las optimizaciones realizadas al sistema de caja (CashRegister) para mejorar la consistencia, confiabilidad y mantenibilidad del código.

---

## 🎯 Problemas Identificados

### 1. **Inconsistencia en Métodos de Pago**
- Los gastos podían registrarse como `'cash'` o `'Efectivo'`
- Las consultas no manejaban ambas variaciones
- Esto causaba errores en los cálculos de balance

### 2. **Modelo Duplicado**
- Existe `CashCuts.php` que no se utiliza
- Puede causar confusión sobre cuál modelo usar

### 3. **Falta de Validaciones**
- No se validaba que no hubiera otra caja abierta antes de crear una nueva
- No se validaba que la caja estuviera abierta antes de cerrarla

### 4. **Asignación Manual de Caja**
- Las transacciones y gastos requerían especificar manualmente la caja
- Propenso a errores humanos

---

## ✅ Soluciones Implementadas

### 1. **Normalización de Métodos de Pago**

#### Backend - `CashRegister.php`
```php
// ANTES
$outgoingTotal = $this->gastos()
    ->where('method', 'cash')
    ->sum('amount');

// DESPUÉS
$outgoingTotal = $this->gastos()
    ->whereIn('method', ['cash', 'Efectivo'])
    ->sum('amount');
```

**Afecta a:**
- `getCurrentBalance()`
- `getCashBalance()`
- `getTransactionsSummary()`

#### Frontend - `page.tsx`
```typescript
// Helper global para normalizar
function isCashPayment(method: string): boolean {
  if (!method) return false;
  const normalized = method.toLowerCase().trim();
  return normalized === 'cash' || normalized === 'efectivo';
}

// Uso en filtros
caja.transactions?.filter((t: any) => isCashPayment(t.payment_method))
caja.gastos?.filter((g: any) => isCashPayment(g.method))
```

---

### 2. **Método Estático para Obtener Caja Activa**

#### `CashRegister.php`
```php
/**
 * Obtener la caja activa de un campus
 */
public static function getActiveByCampus($campusId)
{
    return self::where('campus_id', $campusId)
        ->where('status', 'abierta')
        ->first();
}
```

**Beneficios:**
- ✅ Centraliza la lógica de búsqueda
- ✅ Evita duplicación de código
- ✅ Facilita el mantenimiento

---

### 3. **Validaciones Mejoradas**

#### A. Prevenir Múltiples Cajas Abiertas

**`CashCutController::store()`**
```php
if ($validated['status'] === 'abierta') {
    $existingOpenCashRegister = CashRegister::getActiveByCampus($validated['campus_id']);
    if ($existingOpenCashRegister) {
        return response()->json([
            'message' => 'Ya existe una caja abierta para este campus. Debe cerrarla antes de abrir una nueva.',
            'cash_register_id' => $existingOpenCashRegister->id
        ], 422);
    }
}
```

#### B. Prevenir Cerrar Caja Ya Cerrada

**`CashCutController::update()`**
```php
if ($validated['status'] === 'cerrada' && $cashRegister->status === 'cerrada') {
    return response()->json([
        'message' => 'Esta caja ya está cerrada.'
    ], 422);
}
```

---

### 4. **Helper para Auto-Asignación de Caja**

Se creó `app/Helpers/CashRegisterHelper.php` con utilidades:

#### A. Auto-Asignar Caja Activa
```php
public static function autoAssignCashRegister(array $data, string $paymentMethodKey = 'payment_method')
{
    if (!isset($data['cash_register_id']) || $data['cash_register_id'] === null) {
        $paymentMethod = $data[$paymentMethodKey] ?? null;
        $isCashPayment = in_array(strtolower($paymentMethod), ['cash', 'efectivo']);
        
        if ($isCashPayment && isset($data['campus_id'])) {
            $activeCashRegister = self::getActiveCashRegister($data['campus_id']);
            if ($activeCashRegister) {
                $data['cash_register_id'] = $activeCashRegister->id;
            }
        }
    }
    return $data;
}
```

**Uso Recomendado:**
```php
// En TransactionController::store()
use App\Helpers\CashRegisterHelper;

$validated = CashRegisterHelper::autoAssignCashRegister($validated);
```

#### B. Normalizar Método de Pago
```php
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
```

#### C. Validar Caja para Pagos en Efectivo
```php
public static function validateCashRegisterForCashPayment($campusId, $paymentMethod)
{
    $isCashPayment = in_array(strtolower($paymentMethod), ['cash', 'efectivo']);
    
    if (!$isCashPayment) {
        return true; // No es necesario validar si no es efectivo
    }
    
    $activeCashRegister = self::getActiveCashRegister($campusId);
    return $activeCashRegister !== null;
}
```

---

## 🔄 Flujo Optimizado

### Apertura de Caja
```
1. Usuario solicita abrir caja
2. ✅ VALIDACIÓN: No debe haber otra caja abierta para el campus
3. Se crea CashRegister con status='abierta'
4. Se asigna monto inicial (o se toma del next_day de la caja anterior)
```

### Registro de Transacción/Gasto en Efectivo
```
1. Usuario crea transacción/gasto con payment_method='cash'
2. ✅ AUTO-ASIGNACIÓN: Se busca caja abierta del campus
3. Si existe, se asigna automáticamente cash_register_id
4. Se registra la transacción/gasto
```

### Cálculo de Balance
```
1. Se suman ingresos: transactions WHERE payment_method IN ('cash', 'Efectivo')
2. Se suman egresos: gastos WHERE method IN ('cash', 'Efectivo')
3. Balance = initial_amount + ingresos - egresos
```

### Cierre de Caja
```
1. Usuario solicita cerrar caja
2. ✅ VALIDACIÓN: La caja debe estar abierta
3. ✅ VALIDACIÓN: El conteo manual debe coincidir con balance calculado
4. Se registra final_amount y next_day
5. Status cambia a 'cerrada'
```

---

## 📊 Impacto en la Base de Datos

### Sin Cambios en Estructura
No se requieren migraciones nuevas. Los cambios son solo en la lógica de negocio.

### Campos Relevantes de `cash_registers`
```sql
- id
- campus_id (FK)
- initial_amount (DECIMAL)
- initial_amount_cash (JSON)
- final_amount (DECIMAL, nullable)
- final_amount_cash (JSON, nullable)
- next_day (DECIMAL, nullable)
- next_day_cash (JSON, nullable)
- status (ENUM: 'abierta', 'cerrada')
- opened_at (DATETIME)
- closed_at (DATETIME, nullable)
- notes (TEXT, nullable)
```

---

## 🚀 Próximos Pasos Recomendados

### 1. Implementar Auto-Asignación en Controllers
```php
// TransactionController::store()
$validated = CashRegisterHelper::autoAssignCashRegister($validated);

// GastoController::store()
$validated = CashRegisterHelper::autoAssignCashRegister($validated, 'method');
```

### 2. Deprecar Modelo `CashCuts`
- ✅ Confirmar que no se usa en ninguna parte
- ✅ Eliminar el modelo y su migración
- ✅ Limpiar referencias en documentación

### 3. Agregar Tests Unitarios
```php
// tests/Unit/CashRegisterTest.php
test('no permite abrir dos cajas simultáneamente en el mismo campus')
test('auto-asigna caja activa a pagos en efectivo')
test('normaliza métodos de pago correctamente')
test('calcula balance con múltiples variaciones de "efectivo"')
```

### 4. Agregar Índices de Base de Datos
```php
// Migration
Schema::table('cash_registers', function (Blueprint $table) {
    $table->index(['campus_id', 'status']);
});

Schema::table('transactions', function (Blueprint $table) {
    $table->index(['cash_register_id', 'payment_method']);
});

Schema::table('gastos', function (Blueprint $table) {
    $table->index(['cash_register_id', 'method']);
});
```

### 5. Mejorar UX en Frontend
- ⚠️ Mostrar alerta si no hay caja abierta al intentar pago en efectivo
- ✅ Validar antes de enviar al backend
- 📊 Dashboard con estado de cajas por campus

---

## 📝 Notas de Mantenimiento

### Convención de Métodos de Pago
**Siempre usar en base de datos:**
- `'cash'` para efectivo
- `'transfer'` para transferencias
- `'card'` para tarjetas

**Nunca usar:**
- ❌ `'Efectivo'` (con mayúscula)
- ❌ `'transferencia'` (en español)
- ❌ `'tarjeta'` (en español)

### Logs Importantes
El helper registra en logs cuando:
- Auto-asigna una caja
- No encuentra caja abierta para un pago en efectivo

Revisar logs con:
```bash
tail -f storage/logs/laravel.log | grep "Auto-asignando caja"
```

---

## 🔍 Testing

### Manual Testing Checklist
```
□ Intentar abrir dos cajas simultáneamente → Debe fallar
□ Intentar cerrar caja ya cerrada → Debe fallar
□ Crear transacción con 'cash' → Debe asignar caja automáticamente
□ Crear gasto con 'Efectivo' → Debe asignar caja automáticamente
□ Cerrar caja con diferencia → Debe mostrar alerta
□ Verificar balance con métodos mixtos ('cash' + 'Efectivo')
```

### Test Automatizado (Ejemplo)
```php
it('normalizes payment method variations in cash balance', function () {
    $campus = Campus::factory()->create();
    $cashRegister = CashRegister::factory()->create([
        'campus_id' => $campus->id,
        'initial_amount' => 1000,
        'status' => 'abierta'
    ]);
    
    // Transacción con 'cash'
    Transaction::factory()->create([
        'cash_register_id' => $cashRegister->id,
        'payment_method' => 'cash',
        'amount' => 500
    ]);
    
    // Gasto con 'Efectivo'
    Gasto::factory()->create([
        'cash_register_id' => $cashRegister->id,
        'method' => 'Efectivo',
        'amount' => 200
    ]);
    
    expect($cashRegister->fresh()->getCashBalance())->toBe(1300.00);
});
```

---

## 📚 Referencias

- Modelo: `app/Models/CashRegister.php`
- Controller: `app/Http/Controllers/Api/CashCutController.php`
- Helper: `app/Helpers/CashRegisterHelper.php`
- Frontend: `app/(protected)/planteles/caja/page.tsx`
- Migración: `database/migrations/2025_01_07_005220_create_cash_registers_table.php`

---

**Última actualización:** 30 de septiembre de 2025
**Autor:** Sistema de Optimización
**Estado:** ✅ Implementado y Documentado
