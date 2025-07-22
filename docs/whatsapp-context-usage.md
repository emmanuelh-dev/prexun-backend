# Context API Documentation

## Overview
Sistema simple para almacenar instrucciones para ChatGPT. Solo 3 campos esenciales.

## Context Model Structure

```php
// Fields in the contexts table:
- name: string (unique) - Nombre de la instrucción
- instructions: text - Instrucciones para ChatGPT
- is_active: boolean - Si está activo (default: true)
- created_at/updated_at: timestamps
```

## API Endpoints

### 1. List All Contexts
```
GET /api/contexts/
```
Returns all active contexts.

### 2. Create New Context
```
POST /api/contexts/
Body: {
    "name": "string" (required, unique),
    "instructions": "string" (required)
}
```

### 3. Get Context by ID
```
GET /api/contexts/{context_id}
```

### 4. Get Context by Name
```
POST /api/contexts/by-name
Body: {
    "name": "string"
}
```

### 5. Update Context
```
PUT /api/contexts/{context_id}
Body: {
    "name": "string" (optional),
    "instructions": "string" (optional)
}
```

### 6. Get Instructions
```
GET /api/contexts/{context_id}/instructions
```
Returns the instructions for ChatGPT.

### 7. Activate Context
```
POST /api/contexts/{context_id}/activate
```

### 8. Deactivate Context
```
POST /api/contexts/{context_id}/deactivate
```

### 9. Delete Context
```
DELETE /api/contexts/{context_id}
```

### 10. Get Statistics
```
GET /api/contexts/stats/overview
```

### 11. Create WhatsApp Default Context
```
POST /api/contexts/whatsapp/default
```
Creates a predefined context optimized for WhatsApp chatbots.

## Usage Example

```php
// In your WhatsApp chatbot controller
use App\Models\Context;

class WhatsAppBotController extends Controller
{
    public function handleMessage($userMessage)
    {
        // Get the WhatsApp context
        $context = Context::getByName('whatsapp_default');
        
        if (!$context) {
            // Create default context if it doesn't exist
            $context = Context::createWhatsAppDefault();
        }
        
        // Build prompt for ChatGPT using the context
        $prompt = $this->buildChatGPTPrompt($context, $userMessage);
        
        // Send to ChatGPT and get response
        $response = $this->sendToChatGPT($prompt);
        
        return $response;
    }
    
    private function buildChatGPTPrompt($context, $userMessage)
    {
        // Use the instructions directly
        $prompt = $context->instructions;
        
        // Add the user message
        $fullPrompt = $prompt . "\n\nMensaje del usuario: " . $userMessage;
        
        return $fullPrompt;
    }
    
    private function sendToChatGPT($prompt)
    {
        // Your ChatGPT API integration here
        // Example structure:
        return [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                // Add conversation history if needed
            ]
        ];
    }
}
```

## Example Context Creation

```php
// Create a customer service context
$context = Context::create([
    'name' => 'customer_service',
    'instructions' => 'Eres un asistente de atención al cliente profesional y empático. Responde siempre de manera cortés y busca resolver los problemas del cliente.'
]);
```

## Use Cases

1. **Instrucciones simples**: Diferentes comportamientos para el chatbot
2. **Reutilizable**: Usar la misma instrucción en múltiples conversaciones
3. **Fácil gestión**: Solo nombre e instrucciones

## Advantages

- ✅ Súper simple (solo 3 campos)
- ✅ Fácil de usar
- ✅ Sin complicaciones
- ✅ Directo al grano