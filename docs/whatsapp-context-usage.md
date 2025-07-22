# WhatsApp Context API - Guía de Uso

Este sistema simplificado permite almacenar instrucciones y comportamientos para un chatbot de WhatsApp, sin complicar con historial de conversaciones.

## Estructura del Modelo Context

- `whatsapp_id`: ID único del usuario de WhatsApp
- `instructions`: Instrucciones de comportamiento para el bot
- `user_info`: Información básica del usuario (nombre, preferencias)
- `current_state`: Estado actual (idle, waiting_response, etc.)
- `temp_data`: Datos temporales para flujos específicos
- `last_interaction`: Última interacción
- `is_active`: Si el contexto está activo

## Endpoints API

### 1. Obtener o crear contexto
```http
GET /api/context/{whatsapp_id}
```

### 2. Actualizar instrucciones
```http
POST /api/context/instructions
{
    "whatsapp_id": "5215512345678",
    "instructions": "Comportate como un asistente educativo amigable. Siempre saluda con el nombre del usuario."
}
```

### 3. Actualizar información del usuario
```http
POST /api/context/user-info
{
    "whatsapp_id": "5215512345678",
    "user_info": {
        "name": "Juan Pérez",
        "campus": "Campus Norte",
        "language": "es"
    }
}
```

### 4. Cambiar estado
```http
POST /api/context/state
{
    "whatsapp_id": "5215512345678",
    "state": "waiting_response",
    "temp_data": {
        "question_type": "enrollment",
        "step": 1
    }
}
```

### 5. Reiniciar contexto
```http
POST /api/context/reset/{whatsapp_id}
```

### 6. Desactivar contexto
```http
POST /api/context/deactivate/{whatsapp_id}
```

### 7. Obtener contextos activos
```http
GET /api/context/active/list
```

### 8. Obtener estadísticas
```http
GET /api/context/stats/summary
```

## Ejemplo de Uso en Chatbot

```php
// En tu controlador de WhatsApp
use App\Models\Context;

class WhatsAppController extends Controller
{
    public function handleMessage($whatsappId, $message)
    {
        // Obtener contexto del usuario
        $context = Context::getOrCreateForWhatsApp($whatsappId);
        
        // Preparar prompt para ChatGPT
        $prompt = $this->buildPrompt($context, $message);
        
        // Enviar a ChatGPT y obtener respuesta
        $response = $this->sendToChatGPT($prompt);
        
        // Actualizar última interacción
        $context->touch();
        
        return $response;
    }
    
    private function buildPrompt($context, $message)
    {
        $prompt = "";
        
        // Agregar instrucciones si existen
        if ($context->instructions) {
            $prompt .= "Instrucciones: {$context->instructions}\n\n";
        }
        
        // Agregar información del usuario
        if ($context->user_info) {
            $userInfo = json_encode($context->user_info);
            $prompt .= "Información del usuario: {$userInfo}\n\n";
        }
        
        // Agregar estado actual
        $prompt .= "Estado actual: {$context->current_state}\n\n";
        
        // Agregar datos temporales si existen
        if ($context->temp_data) {
            $tempData = json_encode($context->temp_data);
            $prompt .= "Datos temporales: {$tempData}\n\n";
        }
        
        $prompt .= "Mensaje del usuario: {$message}";
        
        return $prompt;
    }
}
```

## Casos de Uso

1. **Instrucciones personalizadas**: Cada usuario puede tener comportamientos específicos
2. **Estados de conversación**: Manejar flujos como inscripciones, consultas, etc.
3. **Información del usuario**: Recordar nombre, campus, preferencias
4. **Datos temporales**: Almacenar información durante procesos específicos
5. **Gestión de contextos**: Ver usuarios activos, estadísticas, etc.

## Ventajas del Sistema Simplificado

- ✅ Fácil de implementar
- ✅ No almacena historial completo (más eficiente)
- ✅ Enfocado en instrucciones y comportamientos
- ✅ Flexible para diferentes tipos de datos
- ✅ Estados simples para manejar flujos
- ✅ API RESTful clara y directa