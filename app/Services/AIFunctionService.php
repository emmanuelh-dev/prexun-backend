<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\MCPServerService;

/**
 * Service para manejar la integración entre la IA y las funciones MCP
 * Permite que la IA use dinámicamente las funciones auxiliares según necesite
 */
class AIFunctionService
{
    private MCPServerService $mcpServer;
    private string $openAIApiKey;

    public function __construct(MCPServerService $mcpServer)
    {
        $this->mcpServer = $mcpServer;
        $this->openAIApiKey = config('services.openai.api_key');
    }

    /**
     * Procesar mensaje de WhatsApp con capacidades de funciones dinámicas
     */
    public function processWhatsAppMessage(
        string $phoneNumber, 
        string $incomingMessage, 
        array $conversationHistory = []
    ): array {
        try {
            // Paso 1: Buscar información del estudiante por teléfono
            $studentInfo = $this->mcpServer->executeFunction('get_student_by_phone', [
                'phone_number' => $phoneNumber
            ]);

            // Paso 2: Construir contexto dinámico
            $systemMessage = $this->buildDynamicSystemMessage($studentInfo);
            
            // Paso 3: Preparar herramientas disponibles para la IA
            $tools = $this->buildToolsForOpenAI();
            
            // Paso 4: Preparar mensajes para OpenAI
            $messages = $this->prepareMessages($systemMessage, $conversationHistory, $incomingMessage);

            // Paso 5: Llamar a OpenAI con function calling
            $response = $this->callOpenAIWithFunctions($messages, $tools);

            if (!$response['success']) {
                return $response;
            }

            // Paso 6: Procesar respuesta y ejecutar funciones si es necesario
            $processedResponse = $this->processOpenAIResponse($response['data']);

            return [
                'success' => true,
                'response_message' => $processedResponse['final_message'],
                'functions_called' => $processedResponse['functions_executed'],
                'student_info' => $studentInfo['success'] ? $studentInfo['data'] : null,
                'tokens_used' => $response['tokens_used'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Error procesando mensaje con IA y funciones', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno procesando mensaje: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Construir mensaje del sistema dinámico basado en información del estudiante
     */
    private function buildDynamicSystemMessage(array $studentInfo): string
    {
        $baseMessage = "Eres un asistente de WhatsApp para una institución educativa en México. ";
        $baseMessage .= "IMPORTANTE: SIEMPRE responde en ESPAÑOL. Nunca uses inglés. ";
        $baseMessage .= "Responde de manera amigable, profesional y concisa. ";
        $baseMessage .= "Mantén las respuestas cortas ya que es WhatsApp (máximo 2-3 párrafos). ";
        $baseMessage .= "Usa emojis ocasionalmente para hacer la conversación más amigable. ";
        $baseMessage .= "Saluda por su nombre cuando sea posible.\n\n";

        // Agregar información del estudiante si está disponible
        if ($studentInfo['success']) {
            $student = $studentInfo['data'];
            $baseMessage .= "INFORMACIÓN DEL ESTUDIANTE:\n";
            $baseMessage .= "- Matrícula: {$student['matricula']}\n";
            $baseMessage .= "- Nombre: {$student['name']}\n";
            $baseMessage .= "- Email: {$student['email']}\n";
            $baseMessage .= "- Estado: {$student['status']}\n";
            $baseMessage .= "- Campus ID: {$student['campus_id']}\n";
            $baseMessage .= "- Carrera ID: {$student['carrer_id']}\n\n";
            
            $baseMessage .= "IMPORTANTE: Puedes usar las funciones disponibles para obtener información específica ";
            $baseMessage .= "como pagos, asistencias, etc. cuando el estudiante te lo solicite.\n\n";
        } else {
            $baseMessage .= "NOTA: El número de teléfono no está registrado como estudiante. ";
            $baseMessage .= "Aún puedes ayudar con información general o solicitar que proporcionen su matrícula.\n\n";
        }

        // Agregar información sobre funciones disponibles
        $baseMessage .= "FUNCIONES DISPONIBLES:\n";
        $baseMessage .= "Tienes acceso a funciones para consultar:\n";
        $baseMessage .= "- Estado de pagos y transacciones (get_student_payments)\n";
        $baseMessage .= "- Calificaciones completas de Moodle con cursos y actividades (get_student_grades)\n";
        $baseMessage .= "- Calificaciones por teléfono (get_student_grades_by_phone)\n";
        $baseMessage .= "- Información académica básica: promedio, intentos (get_student_academic_info)\n";
        $baseMessage .= "- Registro de asistencias (get_student_attendance)\n";
        $baseMessage .= "- Información de horarios y grupos (get_student_schedule)\n";
        $baseMessage .= "- Perfil completo del estudiante (get_student_profile)\n";
        $baseMessage .= "- Búsqueda de estudiantes por nombre (search_students)\n\n";
        $baseMessage .= "IMPORTANTE: \n";
        $baseMessage .= "- EL ID ES EL MISMO QUE LA MATRÍCULA. Si el estudiante te da su matrícula, úsala como 'id' o 'student_id'\n";
        $baseMessage .= "- Para calificaciones detalladas de Moodle, usa get_student_grades (cursos, actividades, progreso)\n";
        $baseMessage .= "- Para información académica básica (promedio del sistema), usa get_student_academic_info\n";
        $baseMessage .= "- Siempre presenta las calificaciones de forma clara y organizada\n\n";
        $baseMessage .= "Usa estas funciones cuando el estudiante pregunte por información específica.";

        return $baseMessage;
    }

    /**
     * Construir herramientas disponibles en formato OpenAI Functions
     */
    private function buildToolsForOpenAI(): array
    {
        $mcpFunctions = $this->mcpServer->getAvailableFunctions();
        $tools = [];

        foreach ($mcpFunctions as $functionName => $definition) {
            $properties = [];
            $required = [];

            foreach ($definition['parameters'] as $paramName => $paramDef) {
                $properties[$paramName] = [
                    'type' => $paramDef['type'],
                    'description' => $paramDef['description']
                ];

                if ($paramDef['required']) {
                    $required[] = $paramName;
                }
            }

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $functionName,
                    'description' => $definition['description'],
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required
                    ]
                ]
            ];
        }

        return $tools;
    }

    /**
     * Preparar mensajes para OpenAI
     */
    private function prepareMessages(string $systemMessage, array $conversationHistory, string $userMessage): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemMessage]
        ];

        // Agregar historial de conversación
        foreach ($conversationHistory as $message) {
            $messages[] = [
                'role' => $message['direction'] === 'received' ? 'user' : 'assistant',
                'content' => $message['mensaje']
            ];
        }

        // Agregar mensaje actual del usuario
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        return $messages;
    }

    /**
     * Llamar a OpenAI con function calling
     */
    private function callOpenAIWithFunctions(array $messages, array $tools): array
    {
        try {
            if (!$this->openAIApiKey) {
                return [
                    'success' => false,
                    'error' => 'API key de OpenAI no configurada'
                ];
            }

            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7
            ];

            // Solo agregar tools si hay funciones disponibles
            if (!empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = 'auto';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openAIApiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error('Error de OpenAI API con funciones', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Error en la API de OpenAI: ' . $response->status()
                ];
            }

            $data = $response->json();
            
            return [
                'success' => true,
                'data' => $data,
                'tokens_used' => $data['usage']['total_tokens'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Error al conectar con OpenAI para funciones', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con OpenAI'
            ];
        }
    }

    /**
     * Procesar respuesta de OpenAI y ejecutar funciones si es necesario
     */
    private function processOpenAIResponse(array $openAIResponse): array
    {
        $functionsExecuted = [];
        $messages = [$openAIResponse['choices'][0]['message']];
        
        // Verificar si la IA quiere llamar funciones
        $currentMessage = $openAIResponse['choices'][0]['message'];
        
        while (isset($currentMessage['tool_calls']) && !empty($currentMessage['tool_calls'])) {
            // Ejecutar cada función solicitada
            foreach ($currentMessage['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                $functionArgs = json_decode($toolCall['function']['arguments'], true);
                
                Log::info('Ejecutando función MCP solicitada por IA', [
                    'function' => $functionName,
                    'arguments' => $functionArgs
                ]);

                // Ejecutar la función
                $functionResult = $this->mcpServer->executeFunction($functionName, $functionArgs);
                
                $functionsExecuted[] = [
                    'function' => $functionName,
                    'arguments' => $functionArgs,
                    'result' => $functionResult
                ];

                // Agregar resultado de la función a los mensajes
                $messages[] = [
                    'tool_call_id' => $toolCall['id'],
                    'role' => 'tool',
                    'name' => $functionName,
                    'content' => json_encode($functionResult)
                ];
            }

            // Llamar nuevamente a OpenAI con los resultados de las funciones
            $followUpResponse = $this->callOpenAIWithFunctions($messages, []);
            
            if ($followUpResponse['success']) {
                $currentMessage = $followUpResponse['data']['choices'][0]['message'];
                $messages[] = $currentMessage;
            } else {
                break;
            }
        }

        // Obtener el mensaje final de respuesta
        $finalMessage = $currentMessage['content'] ?? 'No se pudo generar respuesta';

        return [
            'final_message' => $finalMessage,
            'functions_executed' => $functionsExecuted,
            'all_messages' => $messages
        ];
    }

    /**
     * Generar respuesta simple sin funciones (fallback)
     */
    public function generateSimpleResponse(string $phoneNumber, string $message, array $conversationHistory = []): array
    {
        try {
            // Buscar información básica del estudiante
            $studentInfo = $this->mcpServer->executeFunction('get_student_by_phone', [
                'phone_number' => $phoneNumber
            ]);

            $systemMessage = "Eres un asistente de WhatsApp para una institución educativa en México. ";
            $systemMessage .= "IMPORTANTE: SIEMPRE responde en ESPAÑOL. Nunca uses inglés. ";
            $systemMessage .= "Responde de manera amigable y profesional. ";
            $systemMessage .= "Usa emojis ocasionalmente para hacer la conversación más amigable. ";
            
            if ($studentInfo['success']) {
                $student = $studentInfo['data'];
                $systemMessage .= "El usuario es el estudiante {$student['name']} con matrícula {$student['matricula']}. ";
                $systemMessage .= "Salúdalo por su nombre y sé amigable. ";
            } else {
                $systemMessage .= "El número no está registrado como estudiante, pero aún puedes ayudar. ";
            }

            $messages = [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $message]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openAIApiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 300,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'response_message' => $data['choices'][0]['message']['content'],
                    'student_info' => $studentInfo['success'] ? $studentInfo['data'] : null
                ];
            }

            return [
                'success' => false,
                'error' => 'Error generando respuesta simple'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }
}