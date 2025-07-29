# Sistema de Adeudos - Documentación Completa

## Resumen

Se ha implementado un sistema completo de gestión de adeudos que permite:
- Crear adeudos asociados a estudiantes y períodos
- Registrar pagos mediante transacciones
- Seguimiento automático del estado de pagos
- Interfaz de usuario completa para gestión

## Estructura de Base de Datos

### Tabla `debts`
```sql
CREATE TABLE debts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    period_id BIGINT UNSIGNED NOT NULL,
    concept VARCHAR(255) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    remaining_amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    INDEX idx_debts_student_id (student_id),
    INDEX idx_debts_period_id (period_id),
    INDEX idx_debts_status (status),
    INDEX idx_debts_due_date (due_date)
);
```

### Modificación a tabla `transactions`
```sql
ALTER TABLE transactions ADD COLUMN debt_id BIGINT UNSIGNED NULL;
ALTER TABLE transactions ADD FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE SET NULL;
CREATE INDEX idx_transactions_debt_id ON transactions(debt_id);
```

## Modelos Laravel

### Debt.php
- **Ubicación**: `app/Models/Debt.php`
- **Relaciones**:
  - `belongsTo(Student::class)`
  - `belongsTo(Period::class)`
  - `hasMany(Transaction::class)`
- **Métodos principales**:
  - `updatePaymentStatus()`: Actualiza automáticamente el estado del adeudo
  - `isOverdue()`: Verifica si el adeudo está vencido
  - `getPaymentPercentageAttribute()`: Calcula el porcentaje pagado
- **Scopes**:
  - `pending()`, `partial()`, `paid()`, `overdue()`
  - `byStudent()`, `byPeriod()`, `byCampus()`

### Actualizaciones en modelos existentes

#### Student.php
```php
public function debts()
{
    return $this->hasMany(Debt::class);
}
```

#### Transaction.php
```php
protected $fillable = [
    // ... campos existentes
    'debt_id'
];

public function debt()
{
    return $this->belongsTo(Debt::class);
}
```

## Controlador

### DebtController.php
- **Ubicación**: `app/Http/Controllers/DebtController.php`
- **Endpoints disponibles**:

#### CRUD Básico
- `GET /api/debts` - Lista paginada con filtros
- `POST /api/debts` - Crear nuevo adeudo
- `GET /api/debts/{id}` - Obtener adeudo específico
- `PUT /api/debts/{id}` - Actualizar adeudo
- `DELETE /api/debts/{id}` - Eliminar adeudo

#### Endpoints Especializados
- `GET /api/debts/student/{studentId}` - Adeudos por estudiante
- `GET /api/debts/period/{periodId}` - Adeudos por período
- `PUT /api/debts/{id}/payment-status` - Actualizar estado de pago
- `GET /api/debts/overdue` - Adeudos vencidos
- `GET /api/debts/summary/stats` - Resumen estadístico
- `GET /api/debts/{id}/transactions` - Transacciones de un adeudo

#### Filtros disponibles
- `student_id`: Filtrar por estudiante
- `period_id`: Filtrar por período
- `status`: Filtrar por estado (pending, partial, paid, overdue)
- `campus_id`: Filtrar por campus
- `search`: Búsqueda por concepto, nombre de estudiante o matrícula

## Integración con Transacciones

### ChargeController.php (Modificado)
Se actualizó el método `store()` para:
1. Aceptar `debt_id` en la validación
2. Asociar transacciones con adeudos
3. Actualizar automáticamente el estado del adeudo cuando se registra un pago

```php
// Validación actualizada
$validated = $request->validate([
    // ... validaciones existentes
    'debt_id' => 'nullable|exists:debts,id',
]);

// Lógica de actualización automática
if ($transaction->debt_id && $transaction->transaction_type === 'income') {
    $debt = Debt::find($transaction->debt_id);
    $debt->updatePaymentStatus();
}
```

## Componentes React

### 1. StudentDebts.jsx
- **Propósito**: Mostrar adeudos en la página específica del estudiante
- **Funcionalidades**:
  - Lista de adeudos del estudiante
  - Crear nuevos adeudos
  - Registrar pagos
  - Resumen de totales

### 2. DebtsList.jsx
- **Propósito**: Lista general de todos los adeudos con filtros avanzados
- **Funcionalidades**:
  - Filtros por campus, período, estado, búsqueda
  - Paginación
  - Resumen estadístico
  - Acciones rápidas (pagar, ver estudiante)

### 3. CreateDebt.jsx
- **Propósito**: Formulario para crear nuevos adeudos
- **Funcionalidades**:
  - Búsqueda de estudiantes
  - Selección de período
  - Validaciones de formulario
  - Modo modal o página completa

### 4. DebtDetailsModal.jsx
- **Propósito**: Modal con detalles completos de un adeudo
- **Funcionalidades**:
  - Información completa del adeudo y estudiante
  - Resumen visual de pagos
  - Formulario para registrar pagos
  - Historial de transacciones

## Estados del Adeudo

1. **pending**: Adeudo sin pagos
2. **partial**: Adeudo con pagos parciales
3. **paid**: Adeudo completamente pagado
4. **overdue**: Adeudo vencido (fecha de vencimiento pasada)

## Flujo de Trabajo

### Crear un Adeudo
1. Seleccionar estudiante y período
2. Definir concepto y monto
3. Establecer fecha de vencimiento
4. El sistema crea el adeudo con estado 'pending'

### Registrar Pagos
1. Crear transacción de tipo 'income' asociada al adeudo
2. El sistema automáticamente:
   - Actualiza `paid_amount` del adeudo
   - Recalcula `remaining_amount`
   - Actualiza el `status` según corresponda

### Seguimiento Automático
- Los adeudos se marcan como 'overdue' automáticamente cuando pasa la fecha de vencimiento
- El estado se actualiza en tiempo real con cada pago registrado

## Rutas API

```php
// Rutas en api.php
Route::middleware('auth:sanctum')->group(function () {
    // CRUD básico
    Route::apiResource('debts', DebtController::class);
    
    // Rutas especializadas
    Route::get('debts/student/{student}', [DebtController::class, 'getByStudent']);
    Route::get('debts/period/{period}', [DebtController::class, 'getByPeriod']);
    Route::put('debts/{debt}/payment-status', [DebtController::class, 'updatePaymentStatus']);
    Route::get('debts/overdue/list', [DebtController::class, 'getOverdueDebts']);
    Route::get('debts/summary/stats', [DebtController::class, 'getSummary']);
    Route::get('debts/{debt}/transactions', [DebtController::class, 'getDebtTransactions']);
});
```

## Ejemplos de Uso

### Crear un Adeudo
```javascript
const response = await axios.post('/api/debts', {
    student_id: 1,
    period_id: 2,
    concept: 'Colegiatura Enero 2025',
    total_amount: 5000.00,
    due_date: '2025-01-31',
    description: 'Pago mensual de colegiatura'
});
```

### Registrar un Pago
```javascript
const response = await axios.post('/api/charges', {
    student_id: 1,
    debt_id: 5,
    transaction_type: 'income',
    amount: 2500.00,
    payment_method: 'efectivo',
    notes: 'Pago parcial de colegiatura'
});
```

### Obtener Adeudos con Filtros
```javascript
const response = await axios.get('/api/debts', {
    params: {
        status: 'pending',
        campus_id: 1,
        search: 'colegiatura',
        page: 1
    }
});
```

## Consideraciones de Seguridad

1. **Autenticación**: Todas las rutas requieren autenticación con Sanctum
2. **Validación**: Validación estricta en todos los endpoints
3. **Integridad**: Claves foráneas con restricciones CASCADE/SET NULL
4. **Auditoría**: Timestamps automáticos en todas las operaciones

## Mejoras Futuras Sugeridas

1. **Notificaciones**: Sistema de alertas para adeudos próximos a vencer
2. **Reportes**: Generación de reportes en PDF/Excel
3. **Descuentos**: Sistema de descuentos y promociones
4. **Planes de Pago**: Adeudos con múltiples fechas de vencimiento
5. **Integración**: Conexión con sistemas de pago externos
6. **Auditoría**: Log detallado de cambios en adeudos

## Archivos Creados/Modificados

### Backend
- `database/migrations/2025_01_27_000000_create_debts_table.php`
- `database/migrations/2025_01_27_000001_add_debt_id_to_transactions_table.php`
- `app/Models/Debt.php`
- `app/Http/Controllers/DebtController.php`
- `app/Models/Student.php` (modificado)
- `app/Models/Transaction.php` (modificado)
- `app/Http/Controllers/ChargeController.php` (modificado)
- `routes/api.php` (modificado)

### Frontend
- `frontend-example/StudentDebts.jsx`
- `frontend-example/DebtsList.jsx`
- `frontend-example/CreateDebt.jsx`
- `frontend-example/DebtDetailsModal.jsx`

## Conclusión

El sistema de adeudos está completamente implementado y funcional. Proporciona una solución robusta para la gestión de deudas estudiantiles con seguimiento automático de pagos, interfaz de usuario intuitiva y API completa para integraciones futuras.