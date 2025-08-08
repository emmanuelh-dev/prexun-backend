<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Context;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * Enviar mensaje al chat y obtener respuesta de OpenAI
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required_without:images|string|max:4000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|max:10240', // 10MB max por imagen
            'include_history' => 'boolean',
            'target_user_id' => 'nullable|exists:users,id',
            'conversation_type' => 'string|in:general,student_support,test_evaluation,academic_guidance,whatsapp_outbound,whatsapp_inbound',
            'related_id' => 'nullable|integer', // ID del estudiante, examen, etc.
            'session_id' => 'nullable|string'
        ]);

        Log::info('Info', $request->all());


        try {
            $user = $request->user();
            $targetUserId = $request->input('target_user_id', $user->id);
            $message = $request->input('message', '[Imagen enviada]');
            $includeHistory = $request->input('include_history', true);
            $conversationType = $request->input('conversation_type', 'general');
            $relatedId = $request->input('related_id');
            $sessionId = $request->input('session_id');
            
            // Si no hay session_id, crear uno nuevo
            if (!$sessionId) {
                $sessionId = ChatMessage::createSession($targetUserId, $conversationType, $relatedId);
            }
            
            $imageUrls = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('chat-images', 'public');
                    $imageUrls[] = Storage::url($path);
                }
            }

            // Guardar mensaje del usuario
            $userMessage = ChatMessage::create([
                'user_id' => $targetUserId,
                'role' => 'user',
                'content' => $message,
                'images' => $imageUrls,
                'conversation_type' => $conversationType,
                'related_id' => $relatedId,
                'session_id' => $sessionId
            ]);

            // Obtener contextos activos
            $activeContexts = Context::where('is_active', true)->get();
            $systemMessage = $this->buildSystemMessage($activeContexts);

            // Preparar historial de conversación
            $conversationHistory = [];
            if ($includeHistory) {
                $conversationHistory = $this->getConversationHistory($targetUserId, $sessionId, $conversationType);
            }

            // Preparar mensaje para OpenAI
            $openAIMessage = $this->prepareOpenAIMessage($message, $imageUrls);

            // Enviar a OpenAI
            $response = $this->sendToOpenAI($systemMessage, $conversationHistory, $openAIMessage);

            if (!$response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el mensaje: ' . $response['error']
                ], 500);
            }

            // Guardar respuesta del asistente
            $assistantMessage = ChatMessage::create([
                'user_id' => $targetUserId,
                'role' => 'assistant',
                'content' => $response['content'],
                'conversation_type' => $conversationType,
                'related_id' => $relatedId,
                'session_id' => $sessionId,
                'metadata' => [
                    'model' => $response['model'] ?? 'gpt-4o-mini',
                    'tokens_used' => $response['tokens_used'] ?? null,
                    'contexts_used' => $activeContexts->pluck('name')->toArray()
                ]
            ]);

            return response()->json([
                'success' => true,
                'response' => $response['content'],
                'session_id' => $sessionId,
                'data' => [
                    'user_message' => $userMessage,
                    'assistant_message' => $assistantMessage,
                    'conversation_id' => $targetUserId,
                    'session_id' => $sessionId,
                    'conversation_type' => $conversationType
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en chat: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener historial de conversación
     */
    public function getHistory(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        $user = $request->user();

        $messages = ChatMessage::getConversationHistory($user->id, $limit);

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    /**
     * Limpiar historial de conversación
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        
        ChatMessage::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Historial limpiado correctamente'
        ]);
    }

    /**
     * Obtener todas las conversaciones (para administradores)
     */
    public function getAllConversations(Request $request): JsonResponse
    {
        $conversations = User::whereHas('chatMessages')
            ->with(['chatMessages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->get()
            ->map(function ($user) {
                $lastMessage = $user->chatMessages->first();
                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ?? 'user'
                    ],
                    'last_message' => $lastMessage ? [
                        'content' => $lastMessage->content,
                        'created_at' => $lastMessage->created_at,
                        'role' => $lastMessage->role
                    ] : null,
                    'message_count' => $user->chatMessages()->count()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }

    /**
     * Obtener historial de conversación de un usuario específico
     */
    public function getUserHistory(Request $request, $userId): JsonResponse
    {
        $request->validate([
            'limit' => 'integer|min:1|max:100'
        ]);

        $limit = $request->input('limit', 50);
        
        // Verificar que el usuario existe
        $user = User::findOrFail($userId);

        $messages = ChatMessage::getConversationHistory($userId, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user'
                ],
                'messages' => $messages
            ]
        ]);
    }

    /**
     * Obtener todas las sesiones de conversación de un usuario
     */
    public function getUserSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessions = ChatMessage::getConversationsBySession($user->id);

        return response()->json([
            'success' => true,
            'sessions' => $sessions
        ]);
    }

    /**
     * Obtener historial de una sesión específica
     */
    public function getSessionHistory(Request $request, $sessionId): JsonResponse
    {
        $user = $request->user();
        
        $messages = ChatMessage::forUser($user->id)
            ->bySession($sessionId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'session_id' => $sessionId
        ]);
    }

    /**
     * Crear una nueva sesión de chat
     */
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_type' => 'required|string|in:general,student_support,test_evaluation,academic_guidance,whatsapp_outbound,whatsapp_inbound',
            'related_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255'
        ]);

        $user = $request->user();
        $conversationType = $request->input('conversation_type');
        $relatedId = $request->input('related_id');
        
        $sessionId = ChatMessage::createSession($user->id, $conversationType, $relatedId);

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'conversation_type' => $conversationType,
            'related_id' => $relatedId
        ]);
    }

    /**
     * Obtener conversaciones por tipo
     */
    public function getConversationsByType(Request $request, $type): JsonResponse
    {
        $user = $request->user();
        
        $conversations = ChatMessage::forUser($user->id)
            ->byConversationType($type)
            ->select('session_id', 'related_id', 'created_at')
            ->whereNotNull('session_id')
            ->groupBy('session_id', 'related_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($type) {
                $messageCount = ChatMessage::where('session_id', $conversation->session_id)->count();
                $lastMessage = ChatMessage::where('session_id', $conversation->session_id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                return [
                    'session_id' => $conversation->session_id,
                    'conversation_type' => $type,
                    'related_id' => $conversation->related_id,
                    'message_count' => $messageCount,
                    'last_message' => $lastMessage ? $lastMessage->content : null,
                    'last_activity' => $lastMessage ? $lastMessage->created_at : $conversation->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'type' => $type
        ]);
    }

    /**
     * Limpiar historial de conversación de un usuario específico
     */
    public function clearUserHistory(Request $request, $userId): JsonResponse
    {
        // Verificar que el usuario existe
        $user = User::findOrFail($userId);
        
        ChatMessage::where('user_id', $userId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Historial del usuario limpiado correctamente'
        ]);
    }

    /**
     * Construir mensaje del sistema basado en contextos activos
     */
    private function buildSystemMessage($contexts): string
    {
        if ($contexts->isEmpty()) {
            return 'Eres un asistente útil y amigable.';
        }

        $instructions = $contexts->map(function ($context) {
            return "{$context->name}: {$context->instructions}";
        })->join('\n\n');

        return "Eres un asistente útil y amigable. Sigue todas estas instrucciones al mismo tiempo:\n\n{$instructions}";
    }

    /**
     * Obtener historial de conversación formateado para OpenAI
     */
    private function getConversationHistory($userId, $sessionId = null, $conversationType = null, $limit = 20): array
    {
        $query = ChatMessage::forUser($userId)->orderBy('created_at', 'desc');
        
        if ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($conversationType && $conversationType !== 'general') {
            $query->where('conversation_type', $conversationType);
        }
        
        $messages = $query->limit($limit)->get()->reverse()->values();

        return $messages->map(function ($message) {
            return [
                'role' => $message->role,
                'content' => $message->content
            ];
        })->toArray();
    }

    /**
     * Preparar mensaje para OpenAI con soporte para imágenes
     */
    private function prepareOpenAIMessage($text, $imageUrls): array
    {
        if (empty($imageUrls)) {
            return ['role' => 'user', 'content' => $text];
        }

        $content = [];
        
        if (!empty($text)) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($imageUrls as $imageUrl) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => url($imageUrl)]
            ];
        }

        return ['role' => 'user', 'content' => $content];
    }

    /**
     * Enviar mensaje a OpenAI
     */
    private function sendToOpenAI($systemMessage, $conversationHistory, $userMessage): array
    {
        try {
            $apiKey = config('services.openai.api_key');
            
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'API key de OpenAI no configurada'
                ];
            }

            $messages = [
                ['role' => 'system', 'content' => $systemMessage],
                ...$conversationHistory,
                $userMessage
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7
            ]);

            if (!$response->successful()) {
                Log::error('Error de OpenAI API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Error en la API de OpenAI: ' . $response->status()
                ];
            }

            $data = $response->json();
        
            Log::info('Respuesta de OpenAI', [
                'model' => $data['model'] ?? 'gpt-4o-mini',
                'usage' => $data['usage'] ?? null,
                'choices' => $data['choices'] ?? []
            ]);
            return [
                'success' => true,
                'content' => $data['choices'][0]['message']['content'] ?? 'No se pudo generar respuesta',
                'model' => $data['model'] ?? 'gpt-4o-mini',
                'tokens_used' => $data['usage']['total_tokens'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Error al conectar con OpenAI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error de conexión con OpenAI'
            ];
        }
    }
}