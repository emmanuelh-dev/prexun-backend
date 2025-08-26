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

      // Formatear conversaciones para compatibilidad con el frontend
      $conversationsWithMessages = collect($conversations->items())->map(function ($conversation) {
        $lastMessage = Mensaje::where('phone_number', $conversation->phone_number)
          ->orderBy('created_at', 'desc')
          ->first();

        return [
          'phone_number' => $conversation->phone_number,
          'message_count' => $conversation->message_count,
          'received_count' => $conversation->received_count,
          'sent_count' => $conversation->sent_count,
          'last_message_time' => $conversation->last_message_time,
          'user' => ['phone_number' => $conversation->phone_number], // Para compatibilidad con sendMessage
          'last_message' => $lastMessage ? [
            'id' => $lastMessage->id,
            'content' => $lastMessage->mensaje,
            'direction' => $lastMessage->direction,
            'message_type' => $lastMessage->message_type,
            'created_at' => $lastMessage->created_at
          ] : null,
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

      // Convertir mensajes de WhatsApp al formato que espera el frontend
      $formattedMessages = $messages->map(function ($message) {
        return [
          'id' => $message->id,
          'mensaje' => $message->mensaje,
          'phone_number' => $message->phone_number,
          'direction' => $message->direction,
          'message_type' => $message->message_type,
          'created_at' => $message->created_at,
          'user_id' => $message->user_id
        ];
      });

      return response()->json([
        'success' => true,
        'data' => [
          'phone_number' => $phoneNumber,
          'message_count' => $formattedMessages->count(),
          'messages' => $formattedMessages
        ]
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
      'message' => 'required|string',
      'phone_number' => 'required|string',
    ]);

    try {
      $user = Auth::user();
      if (!$user) {
        return response()->json([
          'success' => false,
          'message' => 'Usuario no autenticado'
        ], 401);
      }

      // Enviar mensaje a través del controlador de WhatsApp
      $whatsappController = new \App\Http\Controllers\WhatsAppController();
      $whatsappRequest = new Request([
        'phone_number' => $request->phone_number,
        'message' => $request->message
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

      $deletedCount = Mensaje::where('phone_number', $phoneNumber)->delete();

      Log::info('Conversación de WhatsApp eliminada desde chat', [
        'phone_number' => $phoneNumber,
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
