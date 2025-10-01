# Flujo del Sistema de Caja - Diagrama

## 🔄 Flujo Completo

```
┌─────────────────────────────────────────────────────────────────┐
│                    SISTEMA DE CAJA OPTIMIZADO                    │
└─────────────────────────────────────────────────────────────────┘

╔═══════════════════════════════════════════════════════════════════╗
║                      1. APERTURA DE CAJA                          ║
╚═══════════════════════════════════════════════════════════════════╝

    Usuario                Frontend                Backend
       │                      │                       │
       │──Abrir Caja─────────>│                       │
       │                      │                       │
       │                      │─POST /cash-register──>│
       │                      │  campus_id: 1         │
       │                      │  status: 'abierta'    │
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │ Validar│
       │                      │                   │  No    │
       │                      │                   │ existe │
       │                      │                   │  otra  │
       │                      │                   │ abierta│
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │<──✅ CashRegister─────│
       │                      │      created          │
       │<──✅ Caja Abierta────│                       │
       │                      │                       │

╔═══════════════════════════════════════════════════════════════════╗
║                  2. REGISTRO DE TRANSACCIÓN                       ║
╚═══════════════════════════════════════════════════════════════════╝

    Usuario                Frontend                Backend
       │                      │                       │
       │──Pago Efectivo──────>│                       │
       │  $500                │                       │
       │                      │                       │
       │                      │─POST /transactions───>│
       │                      │  payment_method: cash │
       │                      │  campus_id: 1         │
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │  Auto  │
       │                      │                   │ Asignar│
       │                      │                   │  Caja  │
       │                      │                   │ Activa │
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │ Buscar │
       │                      │                   │  Caja  │
       │                      │                   │ Abierta│
       │                      │                   │campus 1│
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │Asignar │
       │                      │                   │cash_   │
       │                      │                   │register│
       │                      │                   │  _id   │
       │                      │                   └───┬────┐
       │                      │                       │
       │                      │<──✅ Transaction──────│
       │                      │    saved              │
       │<──✅ Registrado──────│                       │
       │                      │                       │

╔═══════════════════════════════════════════════════════════════════╗
║                     3. REGISTRO DE GASTO                          ║
╚═══════════════════════════════════════════════════════════════════╝

    Usuario                Frontend                Backend
       │                      │                       │
       │──Gasto Efectivo─────>│                       │
       │  $200                │                       │
       │  "Papelería"         │                       │
       │                      │                       │
       │                      │─POST /gastos─────────>│
       │                      │  method: 'Efectivo'   │
       │                      │  campus_id: 1         │
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │Normaliz│
       │                      │                   │   ar   │
       │                      │                   │'Efectiv│
       │                      │                   │o'→'cash│
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │  Auto  │
       │                      │                   │ Asignar│
       │                      │                   │  Caja  │
       │                      │                   │ Activa │
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │<──✅ Gasto saved──────│
       │                      │                       │
       │<──✅ Registrado──────│                       │
       │                      │                       │

╔═══════════════════════════════════════════════════════════════════╗
║                   4. CÁLCULO DE BALANCE                           ║
╚═══════════════════════════════════════════════════════════════════╝

    Usuario                Frontend                Backend
       │                      │                       │
       │──Ver Balance────────>│                       │
       │                      │                       │
       │                      │─GET /cash-register/1─>│
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │ Sumar  │
       │                      │                   │Ingresos│
       │                      │                   │  WHERE │
       │                      │                   │method  │
       │                      │                   │  IN    │
       │                      │                   │['cash',│
       │                      │                   │'Efectiv│
       │                      │                   │  o']   │
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │ Sumar  │
       │                      │                   │ Gastos │
       │                      │                   │  WHERE │
       │                      │                   │method  │
       │                      │                   │  IN    │
       │                      │                   │['cash',│
       │                      │                   │'Efectiv│
       │                      │                   │  o']   │
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │Balance:│
       │                      │                   │Initial │
       │                      │                   │   +    │
       │                      │                   │Ingresos│
       │                      │                   │   -    │
       │                      │                   │ Gastos │
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │<──Balance: $1300──────│
       │<──Balance: $1300─────│                       │
       │                      │                       │

╔═══════════════════════════════════════════════════════════════════╗
║                     5. CIERRE DE CAJA                             ║
╚═══════════════════════════════════════════════════════════════════╝

    Usuario                Frontend                Backend
       │                      │                       │
       │──Cerrar Caja────────>│                       │
       │  Conteo: $1300       │                       │
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │Calcular│
       │                      │                   │Balance │
       │                      │                   │Esperado│
       │                      │                   └───┬────┘
       │                      │                       │
       │          ┌───────────────────────┐           │
       │          │ ⚠️  Diferencia        │           │
       │          │                       │           │
       │<─────────│ Conteo: $1300         │           │
       │          │ Esperado: $1300       │           │
       │          │ Diferencia: $0        │           │
       │          │                       │           │
       │          │ ¿Continuar?           │           │
       │          └───────────────────────┘           │
       │                      │                       │
       │──✅ Confirmar───────>│                       │
       │                      │                       │
       │                      │─PUT /cash-register/1─>│
       │                      │  final_amount: 1300   │
       │                      │  status: 'cerrada'    │
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │Validar │
       │                      │                   │  caja  │
       │                      │                   │ abierta│
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │                   ┌───┴────┐
       │                      │                   │Guardar │
       │                      │                   │final_  │
       │                      │                   │amount  │
       │                      │                   │closed_ │
       │                      │                   │  at    │
       │                      │                   │status= │
       │                      │                   │cerrada │
       │                      │                   └───┬────┘
       │                      │                       │
       │                      │<──✅ Cerrada──────────│
       │<──✅ Caja Cerrada────│                       │
       │                      │                       │

```

## 📊 Estructura de Datos

### CashRegister (Tabla Principal)
```json
{
  "id": 1,
  "campus_id": 1,
  "initial_amount": 1000.00,
  "initial_amount_cash": {
    "1000": 1,
    "500": 0,
    "200": 0,
    ...
  },
  "final_amount": 1300.00,
  "final_amount_cash": {
    "1000": 1,
    "200": 1,
    "100": 1,
    ...
  },
  "next_day": 300.00,
  "next_day_cash": {
    "200": 1,
    "100": 1
  },
  "status": "cerrada",
  "opened_at": "2025-09-30 08:00:00",
  "closed_at": "2025-09-30 20:00:00",
  "notes": "Cierre sin observaciones"
}
```

### Transaction (Relación 1:N)
```json
{
  "id": 123,
  "cash_register_id": 1,  // ← Auto-asignado
  "student_id": 456,
  "campus_id": 1,
  "payment_method": "cash",
  "amount": 500.00,
  "paid": true,
  "transaction_type": "income",
  "payment_date": "2025-09-30 10:30:00"
}
```

### Gasto (Relación 1:N)
```json
{
  "id": 789,
  "cash_register_id": 1,  // ← Auto-asignado
  "campus_id": 1,
  "method": "Efectivo",   // ← Normalizado a 'cash'
  "amount": 200.00,
  "concept": "Papelería",
  "category": "Administrativo",
  "date": "2025-09-30 14:00:00"
}
```

## 🔍 Validaciones Clave

### 1. Apertura de Caja
```
✅ No debe haber otra caja abierta en el mismo campus
✅ El monto inicial debe ser >= 0
✅ Si initial_amount = 0, tomar del next_day anterior
```

### 2. Registro de Transacción/Gasto en Efectivo
```
✅ Auto-asignar cash_register_id si method IN ['cash', 'Efectivo']
⚠️  Advertir si no hay caja abierta (no bloquear)
✅ Normalizar método de pago
```

### 3. Cálculo de Balance
```
✅ Incluir TODAS las variaciones de efectivo
✅ Redondear a 2 decimales
✅ Balance = initial_amount + ingresos - gastos
```

### 4. Cierre de Caja
```
✅ La caja debe estar abierta
✅ Comparar conteo manual vs balance calculado
⚠️  Mostrar diferencia si existe
✅ Permitir cerrar con diferencia (previa confirmación)
✅ Guardar next_day para siguiente caja
```

---

**Última actualización:** 30 de septiembre de 2025
