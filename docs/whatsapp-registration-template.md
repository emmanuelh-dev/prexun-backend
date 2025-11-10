# Plantilla de WhatsApp - Registro Exitoso de Estudiante

## Descripción

Esta plantilla se envía automáticamente cuando un estudiante es registrado exitosamente en el sistema. Notifica al estudiante sobre su registro y le proporciona su matrícula.

## Configuración en WhatsApp Business Manager

### 1. Crear la Plantilla

Ve a **Meta Business Suite** > **WhatsApp** > **Plantillas de mensajes** > **Crear plantilla**

### 2. Información Básica

- **Nombre de la plantilla**: `mensaje_registro_exitoso`
- **Categoría**: Marketing o Transaccional
- **Idioma**: Spanish (es)

### 3. Contenido de la Plantilla

#### Cuerpo del Mensaje

```
¡Bienvenido(a)!

Tu registro ha sido exitoso.

Tu matrícula es: {{1}}

Con esta matrícula podrás acceder a todos los servicios de la institución.

Para ver tus calificaciones o pagos, escribe:
"Dame mis calificaciones" o "Dame mis pagos"

¡Éxito en tu formación académica!
```

#### Parámetros

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| {{1}} | Matrícula del estudiante | 12345 |

#### Pie de Página (Opcional)

```
Si tienes dudas, contáctanos.
```

### 4. Muestra de Variables

En la sección "Muestras de variables", proporciona un ejemplo:

- **{{1}}**: `12345`

### 5. Enviar para Aprobación

Una vez configurada la plantilla:
1. Revisa todos los campos
2. Haz clic en "Enviar"
3. Espera la aprobación de Meta (puede tardar hasta 24 horas)

## Implementación en el Sistema

### Cuándo se Envía

La plantilla se envía automáticamente después de:

1. ✅ Registro exitoso del estudiante en el sistema
2. ✅ Creación exitosa del usuario en Moodle
3. ✅ Asignación exitosa a cohortes
4. ✅ Confirmación de la transacción en la base de datos

### Código Relevante

El envío se realiza en `StudentController::store()`:

```php
// Después del commit de la transacción
DB::commit();
$student->load('charges');

// Log student creation event
StudentEvent::createEvent($student->id, StudentEvent::EVENT_CREATED, null, $student->toArray());

// Enviar plantilla de WhatsApp de registro exitoso
if ($student->phone) {
  $this->sendRegistrationWhatsAppTemplate($student);
}
```

### Parámetros Enviados

```php
'template' => [
  'name' => 'mensaje_registro_exitoso',
  'language' => [
    'code' => 'es'
  ],
  'components' => [
    [
      'type' => 'body',
      'parameters' => [
        [
          'type' => 'text',
          'text' => (string) $student->id  // Matrícula
        ]
      ]
    ]
  ]
]
```

## Requisitos

### Variables de Entorno

Asegúrate de tener configuradas en tu `.env`:

```env
WHATSAPP_TOKEN=tu_token_de_acceso
PHONE_NUMBER_ID=tu_phone_number_id
```

### Campo de Teléfono

- El estudiante **debe tener un número de teléfono** registrado
- El número debe estar en formato internacional: `+52XXXXXXXXXX`
- Si no tiene teléfono, la plantilla no se enviará (se registra un warning en logs)

## Logs

El sistema registra toda la actividad relacionada con el envío:

### Envío Exitoso
```
WhatsApp registration template sent successfully
- student_id: 12345
- matricula: 12345
- phone_number: +525512345678
```

### Error en el Envío
```
Failed to send WhatsApp registration template
- student_id: 12345
- phone_number: +525512345678
- status: 400
- error: {detalles del error}
```

### Credenciales No Configuradas
```
WhatsApp credentials not configured, skipping registration template
- student_id: 12345
```

## Personalización

Si deseas modificar el mensaje o agregar más parámetros:

1. **En WhatsApp Business Manager**: Edita la plantilla y envía nuevamente para aprobación
2. **En el código**: Modifica el método `sendRegistrationWhatsAppTemplate` en `StudentController.php`

### Ejemplo con Más Parámetros

Si quisieras agregar el nombre del estudiante:

**En WhatsApp**:
```
¡Bienvenido(a) {{1}}!

Tu registro ha sido exitoso.
Tu matrícula es: {{2}}
```

**En el código**:
```php
'parameters' => [
  [
    'type' => 'text',
    'text' => $student->firstname . ' ' . $student->lastname
  ],
  [
    'type' => 'text',
    'text' => (string) $student->id
  ]
]
```

## Solución de Problemas

### La plantilla no se envía

1. **Verificar que el teléfono esté registrado**
   ```sql
   SELECT id, firstname, lastname, phone FROM students WHERE id = 12345;
   ```

2. **Verificar formato del teléfono**
   - Debe incluir código de país: `+52XXXXXXXXXX`
   - Sin espacios ni caracteres especiales

3. **Verificar estado de la plantilla en Meta**
   - La plantilla debe estar APROBADA
   - El nombre debe coincidir exactamente: `mensaje_registro_exitoso`
   - El idioma debe ser: `es`

4. **Verificar credenciales**
   ```bash
   php artisan tinker
   >>> env('WHATSAPP_TOKEN')
   >>> env('PHONE_NUMBER_ID')
   ```

5. **Revisar logs**
   ```bash
   tail -f storage/logs/laravel.log | grep "WhatsApp registration"
   ```

### Error: "Template name does not exist"

- La plantilla no existe o no está aprobada en WhatsApp Business
- El nombre de la plantilla no coincide exactamente
- El idioma no coincide

### Error: "Invalid phone number"

- El número no está en formato internacional
- El número no está registrado en WhatsApp
- El número está bloqueado

## Testing

Para probar el envío manualmente:

```bash
curl -X POST https://tu-api.com/api/students \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer tu_token" \
  -d '{
    "firstname": "Juan",
    "lastname": "Pérez",
    "email": "juan.perez@example.com",
    "phone": "+525512345678",
    "type": "preparatoria",
    "campus_id": 1
  }'
```

Verifica los logs para confirmar el envío exitoso.

## Mejores Prácticas

1. **Siempre valida el formato del teléfono** antes de registrar al estudiante
2. **Notifica al usuario** en la UI si el envío falla
3. **Mantén logs detallados** para debugging
4. **No bloquees el registro** si falla el envío de WhatsApp
5. **Implementa retry logic** para casos de fallo temporal
6. **Respeta las políticas de WhatsApp** sobre mensajes masivos

## Consideraciones de Privacidad

- Solo se envía la matrícula, no información sensible
- El número de teléfono se normaliza pero no se almacena en logs públicos
- Los tokens de WhatsApp deben mantenerse seguros en variables de entorno
- Cumple con GDPR/LGPD si aplica

## Referencias

- [WhatsApp Business API - Message Templates](https://developers.facebook.com/docs/whatsapp/api/messages/message-templates)
- [Meta Business Suite](https://business.facebook.com/)
- [Documentación del sistema de plantillas](./whatsapp-template-variables.md)
