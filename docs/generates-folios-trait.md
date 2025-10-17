# GeneratesFolios Trait

Trait para la generación de folios en transacciones. Extrae toda la lógica de generación de folios del `TransactionController` para hacerla reutilizable en otros componentes.

## Uso

```php
use App\Traits\GeneratesFolios;

class YourController extends Controller
{
    use GeneratesFolios;
    
    // Ahora tienes acceso a todos los métodos del trait
}
```

## Métodos Disponibles

### `shouldGenerateSpecificFolio($paymentMethod, $cardId = null)`

Determina si se debe generar un folio específico por método de pago o usar el folio general.

**Parámetros:**
- `$paymentMethod`: Método de pago (cash, transfer, card)
- `$cardId`: ID de la tarjeta (opcional)

**Retorna:** `bool`

**Lógica:**
- `cash`: Siempre retorna `true` (folio específico E)
- `transfer`: Retorna `true` si no hay tarjeta o si la tarjeta no tiene SAT
- `card`: Retorna `true` si hay tarjeta y no tiene SAT

---

### `generateMonthlyFolio($campusId)`

Genera un folio secuencial que se reinicia cada mes.

**Parámetros:**
- `$campusId`: ID del campus

**Retorna:** `int` - El siguiente número de folio para el mes actual

---

### `generatePaymentMethodFolio($campusId, $paymentMethod, $cardId = null)`

Genera folios específicos según el método de pago.

**Parámetros:**
- `$campusId`: ID del campus
- `$paymentMethod`: Método de pago
- `$cardId`: ID de la tarjeta (opcional)

**Retorna:** `array|null`
```php
[
    'column' => 'folio_transfer', // o folio_cash, folio_card
    'value' => 123,               // número secuencial
    'formatted' => 'A0123'        // folio formateado con prefijo
]
```

**Prefijos:**
- Transfer (A): `A0001`, `A0002`, etc.
- Cash (E): `E0001`, `E0002`, etc.
- Card (T): `T0001`, `T0002`, etc.

---

### `folioNew($campusId, $paymentMethod, $cardId = null, $payment_date = null)`

Genera el prefijo del folio nuevo con formato: `{LetraCampus}{Tipo}-{MesAño} | `

**Parámetros:**
- `$campusId`: ID del campus
- `$paymentMethod`: Método de pago
- `$cardId`: ID de la tarjeta (opcional)
- `$payment_date`: Fecha de pago (opcional, usa fecha actual si no se provee)

**Retorna:** `string` - Ejemplo: `"GI-1024 | "` (campus Guadalajara, Ingreso, octubre 2024)

**Tipos de folio:**
- `I`: Ingreso (transfer con SAT o card)
- `A`: Transferencia sin SAT
- `E`: Efectivo

---

### `getDisplayFolio($transaction)`

Obtiene el folio formateado para mostrar según el método de pago y configuración.

**Parámetros:**
- `$transaction`: Objeto Transaction

**Retorna:** `string` - Folio formateado para mostrar

**Ejemplos:**
- Transfer con SAT: `"G123"` (usa folio_new)
- Transfer sin SAT: `"GA0123"` (usa folio_transfer)
- Cash: `"GE0123"` (usa folio_cash)
- Card con tarjeta SAT: `"G123"` (usa folio_new)
- Card sin tarjeta o sin SAT: `"GT0123"` (usa folio_card)

---

## Ejemplo Completo

```php
use App\Traits\GeneratesFolios;

class TransactionController extends Controller
{
    use GeneratesFolios;
    
    public function store(Request $request)
    {
        $transaction = new Transaction($request->validated());
        
        // Verificar si necesita folio específico
        if ($this->shouldGenerateSpecificFolio($transaction->payment_method, $transaction->card_id)) {
            // Generar folio específico por método de pago
            $paymentFolio = $this->generatePaymentMethodFolio(
                $transaction->campus_id,
                $transaction->payment_method,
                $transaction->card_id
            );
            
            if ($paymentFolio) {
                $transaction->{$paymentFolio['column']} = $paymentFolio['value'];
            }
        } else {
            // Generar folio mensual general
            $transaction->folio = $this->generateMonthlyFolio($transaction->campus_id);
        }
        
        // Generar folio_new
        $transaction->folio_new = $this->folioNew(
            $transaction->campus_id,
            $transaction->payment_method,
            $transaction->card_id,
            $transaction->payment_date
        );
        
        $transaction->save();
        
        // Obtener folio para mostrar
        $displayFolio = $this->getDisplayFolio($transaction);
        
        return response()->json([
            'transaction' => $transaction,
            'display_folio' => $displayFolio
        ]);
    }
}
```

## Beneficios

1. **Reutilizable**: Puedes usar el trait en cualquier controlador o clase que necesite generar folios
2. **Mantenible**: Toda la lógica de folios está centralizada en un solo lugar
3. **Testeable**: Los métodos son más fáciles de probar de forma aislada
4. **Escalable**: Si necesitas agregar nuevos tipos de folios, solo modificas el trait
5. **Clean Code**: El `TransactionController` ahora es más pequeño y enfocado en su responsabilidad principal

## Notas Importantes

- Los folios se reinician mensualmente
- Los folios específicos por método de pago son independientes entre sí
- Las tarjetas con `sat = true` usan el folio general en lugar del específico
- El trait usa los modelos `Card`, `Campus` y `Transaction`
