<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Mensaje;
use App\Models\ChatMessage;
use App\Models\User;

class WhatsAppChatController extends Controller
{
    /**
     * Obtener todas las conversaciones de WhatsApp con formato compatible con chat
     */
    public function getConversations(Request $request)
    {
        try {
            $limit = $request->get('limit', 20);
            $page = $request->get('page', 1);
            $search = $request->get('search');

            // Obtener conversaciones de WhatsApp
            $whatsappConversations = collect();
            
            // Obtener conversaciones agrupadas por número de teléfono desde mensajes
            $query = Mensaje::select([
                'phone_number',
                DB::raw('MAX(created_at) as last_message_time'),
                DB::raw('COUNT(*) as message_count'),
                DB::raw('SUM(CASE WHEN direction = "received" THEN 1 ELSE 0 END) as received_count'),
                DB::raw('SUM(CASE WHEN direction = "sent" THEN 1 ELSE 0 END) as sent_count')
            ])
            ->whereNotNull('phone_number')
            ->groupBy('phone_number');

            if ($search) {
                $query->where('phone_number', 'LIKE', "%{$search}%");
            }

            $conversations = $query->orderBy('last_message_time', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            // Formatear conversaciones para compatibilidad con el chat
            $conversationsWithMessages = collect($conversations->items())->map(function ($conversation) {
                $lastMessage = Mensaje::where('phone_number', $conversation->phone_number)
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Crear un usuario ficticio para compatibilidad
                $user = $this->createVirtualUserFromPhone($conversation->phone_number);

                return [
                    'user_id' => $user['id'], // ID virtual basado en teléfono
                    'user' => $user,
                    'last_message' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'user_id' => $user['id'],
                        'role' => $lastMessage->direction === 'sent' ? 'assistant' : 'user',
                        'content' => $lastMessage->mensaje,
                        'created_at' => $lastMessage->created_at,
                        'images' => [],
                        'metadata' => [
                            'platform' => 'whatsapp',
                            'phone_number' => $conversation->phone_number,
                            'message_type' => $lastMessage->message_type
                        ]
                    ] : null,
                    'unread_count' => $conversation->received_count, // Mensajes recibidos como no leídos
                    'contact_info' => $this->getContactInfo($conversation->phone_number),
                    'messages' => [] // Se cargarán por separado
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'conversations' => $conversationsWithMessages,
                    'pagination' => [
                        'current_page' => $conversations->currentPage(),
                        'last_page' => $conversations->lastPage(),
                        'per_page' => $conversations->perPage(),
                        'total' => $conversations->total(),
                        'from' => $conversations->firstItem(),
                        'to' => $conversations->lastItem()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo conversaciones de WhatsApp para chat', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conversaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de mensajes de WhatsApp por número de teléfono
     */
    public function getHistory($phoneNumber)
    {
        try {
            $messages = Mensaje::where('phone_number', $phoneNumber)
                ->orderBy('created_at', 'asc')
                ->get();

            // Convertir mensajes de WhatsApp a formato de chat
            $formattedMessages = $messages->map(function ($message) {
                $user = $this->createVirtualUserFromPhone($message->phone_number);
                
                return [
                    'id' => $message->id,
                    'user_id' => $user['id'],
                    'role' => $message->direction === 'sent' ? 'assistant' : 'user',
                    'content' => $message->mensaje,
                    'images' => [],
                    'metadata' => [
                        'platform' => 'whatsapp',
                        'phone_number' => $message->phone_number,
                        'message_type' => $message->message_type,
                        'direction' => $message->direction
                    ],
                    'created_at' => $message->created_at,
                    'user' => $user
                ];
            });

            return response()->json([
                'success' => true,
                'messages' => $formattedMessages,
                'session_id' => 'whatsapp_' . $phoneNumber . '_' . time()
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo historial de WhatsApp', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar mensaje de WhatsApp a través del chat
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'target_user_id' => 'required|string', // En realidad será el número de teléfono codificado
        ]);

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Decodificar el número de teléfono del target_user_id
            $phoneNumber = $this->decodePhoneFromUserId($request->target_user_id);
            
            if (!$phoneNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Número de teléfono inválido'
                ], 400);
            }

            // Enviar mensaje a través del controlador de WhatsApp
            $whatsappController = new \App\Http\Controllers\WhatsAppController();
            $whatsappRequest = new Request([
                'phone_number' => $phoneNumber,
                'message' => $request->content
            ]);

            $response = $whatsappController->sendMessage($whatsappRequest);
            
            if ($response->getStatusCode() === 200) {
                // Mensaje enviado exitosamente
                return response()->json([
                    'success' => true,
                    'message' => 'Mensaje enviado exitosamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar mensaje de WhatsApp'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error enviando mensaje de WhatsApp desde chat', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar conversación de WhatsApp
     */
    public function clearHistory($phoneNumber)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Decodificar teléfono si viene codificado
            $actualPhoneNumber = $this->decodePhoneFromUserId($phoneNumber) ?: $phoneNumber;

            $deletedCount = Mensaje::where('phone_number', $actualPhoneNumber)->delete();

            Log::info('Conversación de WhatsApp eliminada desde chat', [
                'phone_number' => $actualPhoneNumber,
                'deleted_messages' => $deletedCount,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conversación eliminada exitosamente',
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error eliminando conversación de WhatsApp desde chat', [
                'error' => $e->getMessage(),
                'phone_number' => $phoneNumber
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar conversación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear usuario virtual basado en número de teléfono
     */
    private function createVirtualUserFromPhone($phoneNumber)
    {
        // Crear un ID único basado en el número de teléfono
        $virtualId = crc32($phoneNumber);
        
        // Obtener información del contacto
        $contactInfo = $this->getContactInfo($phoneNumber);
        
        return [
            'id' => $virtualId,
            'name' => $contactInfo['display_name'],
            'email' => $phoneNumber . '@whatsapp.contact',
            'role' => 'WhatsApp Contact',
            'phone_number' => $phoneNumber,
            'is_virtual' => true
        ];
    }

    /**
     * Decodificar número de teléfono desde user_id virtual
     */
    private function decodePhoneFromUserId($userId)
    {
        // Si ya es un número de teléfono, devolverlo
        if (str_starts_with($userId, '+') || preg_match('/^\d+$/', $userId)) {
            return $userId;
        }

        // Intentar buscar en mensajes por user_id virtual
        $message = Mensaje::select('phone_number')
            ->whereRaw('CRC32(phone_number) = ?', [$userId])
            ->first();

        return $message ? $message->phone_number : null;
    }

    /**
     * Obtener información del contacto basada en el número de teléfono
     */
    private function getContactInfo($phoneNumber)
    {
        // Buscar si el número pertenece a un estudiante
        $student = null;

        // Si tienes una tabla de estudiantes con campo de teléfono, puedes activar esto:
        /*
        $student = \App\Models\Student::where('telefono', $phoneNumber)
            ->orWhere('telefono_padre', $phoneNumber)
            ->orWhere('telefono_madre', $phoneNumber)
            ->first();
        */

        return [
            'name' => $student ? $student->name : null,
            'display_name' => $student ? $student->name : $phoneNumber,
            'avatar' => null,
            'is_student' => $student ? true : false,
            'student_id' => $student ? $student->id : null
        ];
    }
}
