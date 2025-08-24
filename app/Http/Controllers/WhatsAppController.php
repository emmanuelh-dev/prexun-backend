<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ChatMessage;
use App\Models\Mensaje;

class WhatsAppController extends Controller
{
  private $whatsappToken;
  private $phoneNumberId;
  private $apiUrl;

  public function __construct()
  {
    $this->whatsappToken = env('WHATSAPP_TOKEN');
    $this->phoneNumberId = env('PHONE_NUMBER_ID');
    $this->apiUrl = "https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages";
  }

  /**
   * Send a simple text message to an existing contact
   */
  public function sendMessage(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => [
        'required',
        'string',
        'regex:/^\+?[1-9]\d{1,14}$/',
        'min:10',
        'max:15'
      ],
      'message' => [
        'required',
        'string',
        'min:1',
        'max:4096'
      ]
    ], [
      'phone_number.required' => 'El número de teléfono es obligatorio',
      'phone_number.regex' => 'El formato del número de teléfono no es válido. Debe incluir código de país (ej: +52XXXXXXXXXX)',
      'phone_number.min' => 'El número de teléfono debe tener al menos 10 dígitos',
      'phone_number.max' => 'El número de teléfono no puede tener más de 15 dígitos',
      'message.required' => 'El mensaje es obligatorio',
      'message.min' => 'El mensaje no puede estar vacío',
      'message.max' => 'El mensaje no puede exceder 4096 caracteres'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    if (!$this->whatsappToken || !$this->phoneNumberId) {
      return response()->json([
        'success' => false,
        'message' => 'Las credenciales de WhatsApp no están configuradas correctamente'
      ], 500);
    }

    // Normalize phone number format
    $phoneNumber = $this->normalizePhoneNumber($request->phone_number);

    $messageData = [
      'messaging_product' => 'whatsapp',
      'to' => $phoneNumber,
      'type' => 'text',
      'text' => [
        'preview_url' => false,
        'body' => $request->message
      ]
    ];

    try {
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->whatsappToken,
        'Content-Type' => 'application/json'
      ])->post($this->apiUrl, $messageData);

      if ($response->successful()) {
        Log::info('WhatsApp message sent successfully', [
          'phone_number' => $request->phone_number,
          'response' => $response->json()
        ]);

        // Registrar en el sistema de chat
        $this->logWhatsAppMessage(
          $request->phone_number,
          $request->message,
          'text',
          null,
          false
        );

        // Almacenar en la tabla mensajes
        $this->storeMensaje($request->message, $request->phone_number, 'text', 'sent');

        return response()->json([
          'success' => true,
          'message' => 'Message sent successfully',
          'data' => $response->json()
        ]);
      } else {
        Log::error('WhatsApp API error', [
          'phone_number' => $request->phone_number,
          'status' => $response->status(),
          'response' => $response->json()
        ]);

        return response()->json([
          'success' => false,
          'message' => 'Failed to send message',
          'error' => $response->json()
        ], $response->status());
      }
    } catch (\Exception $e) {
      Log::error('WhatsApp message sending failed', [
        'phone_number' => $request->phone_number,
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error sending message: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Send a template message to a new user
   */
  public function sendTemplateMessage(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => [
        'required',
        'string',
        'regex:/^\+?[1-9]\d{1,14}$/',
        'min:10',
        'max:15'
      ],
      'template_name' => [
        'required',
        'string',
        'min:1',
        'max:100',
        'regex:/^[a-z0-9_]+$/'
      ],
      'language_code' => [
        'nullable',
        'string',
        'size:2',
        'regex:/^[a-z]{2}$/'
      ]
    ], [
      'phone_number.required' => 'El número de teléfono es obligatorio',
      'phone_number.regex' => 'El formato del número de teléfono no es válido. Debe incluir código de país (ej: +52XXXXXXXXXX)',
      'phone_number.min' => 'El número de teléfono debe tener al menos 10 dígitos',
      'phone_number.max' => 'El número de teléfono no puede tener más de 15 dígitos',
      'template_name.required' => 'El nombre de la plantilla es obligatorio',
      'template_name.regex' => 'El nombre de la plantilla solo puede contener letras minúsculas, números y guiones bajos',
      'template_name.min' => 'El nombre de la plantilla no puede estar vacío',
      'template_name.max' => 'El nombre de la plantilla no puede exceder 100 caracteres',
      'language_code.size' => 'El código de idioma debe tener exactamente 2 caracteres',
      'language_code.regex' => 'El código de idioma debe contener solo letras minúsculas (ej: es, en)'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    if (!$this->whatsappToken || !$this->phoneNumberId) {
      return response()->json([
        'success' => false,
        'message' => 'Las credenciales de WhatsApp no están configuradas correctamente'
      ], 500);
    }

    // Normalize phone number format
    $phoneNumber = $this->normalizePhoneNumber($request->phone_number);

    $messageData = [
      'messaging_product' => 'whatsapp',
      'to' => $phoneNumber,
      'type' => 'template',
      'template' => [
        'name' => $request->template_name,
        'language' => [
          'code' => $request->language_code ?? 'es'
        ]
      ]
    ];

    try {
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->whatsappToken,
        'Content-Type' => 'application/json'
      ])->post($this->apiUrl, $messageData);

      if ($response->successful()) {
        Log::info('WhatsApp template message sent successfully', [
          'phone_number' => $request->phone_number,
          'template' => $request->template_name,
          'response' => $response->json()
        ]);

        // Registrar en el sistema de chat
        $this->logWhatsAppMessage(
          $request->phone_number,
          '', // No hay mensaje de texto en templates
          'template',
          $request->template_name,
          true
        );

        // Almacenar en la tabla mensajes
        $this->storeMensaje("Template: {$request->template_name}", $request->phone_number, 'template', 'sent');

        return response()->json([
          'success' => true,
          'message' => 'Template message sent successfully',
          'data' => $response->json()
        ]);
      } else {
        Log::error('WhatsApp template API error', [
          'phone_number' => $request->phone_number,
          'template' => $request->template_name,
          'status' => $response->status(),
          'response' => $response->json()
        ]);

        return response()->json([
          'success' => false,
          'message' => 'Failed to send template message',
          'error' => $response->json()
        ], $response->status());
      }
    } catch (\Exception $e) {
      Log::error('WhatsApp template message sending failed', [
        'phone_number' => $request->phone_number,
        'template' => $request->template_name,
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error sending template message: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get WhatsApp configuration status
   */
  public function getStatus()
  {
    return response()->json([
      'configured' => !empty($this->whatsappToken) && !empty($this->phoneNumberId),
      'phone_number_id' => $this->phoneNumberId ? 'Configured' : 'Not configured',
      'token' => $this->whatsappToken ? 'Configured' : 'Not configured'
    ]);
  }

  /**
   * Webhook para recibir mensajes de WhatsApp
   */
  public function receiveMessage(Request $request)
  {
    try {
      Log::info('WhatsApp webhook recibido', ['payload' => $request->all()]);

      $body = $request->all();

      // Verificar que es un mensaje válido
      if (!isset($body['entry'][0]['changes'][0]['value']['messages'][0])) {
        return response()->json(['status' => 'ok']);
      }

      $message = $body['entry'][0]['changes'][0]['value']['messages'][0];
      $phoneNumber = $message['from'];

      // Obtener contenido del mensaje según el tipo
      $content = '';
      $messageType = $message['type'];

      switch ($messageType) {
        case 'text':
          $content = $message['text']['body'];
          break;
        case 'image':
          $content = '[Imagen recibida]';
          break;
        case 'audio':
          $content = '[Audio recibido]';
          break;
        case 'document':
          $content = '[Documento recibido]';
          break;
        default:
          $content = "[Mensaje de tipo: {$messageType}]";
      }

      // Almacenar mensaje recibido
      $this->storeMensaje($content, $phoneNumber, $messageType, 'received');

      Log::info('Mensaje de WhatsApp recibido y almacenado', [
        'from' => $phoneNumber,
        'type' => $messageType,
        'content' => $content
      ]);

      return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
      Log::error('Error procesando webhook de WhatsApp', [
        'error' => $e->getMessage(),
        'payload' => $request->all()
      ]);

      return response()->json(['status' => 'error'], 500);
    }
  }

  /**
   * Verificación del webhook de WhatsApp
   */
  public function verifyWebhook(Request $request)
  {
    $verifyToken = env('WHATSAPP_VERIFY_TOKEN');
    $mode = $request->query('hub_mode');
    $token = $request->query('hub_verify_token');
    $challenge = $request->query('hub_challenge');

    if ($mode === 'subscribe' && $token === $verifyToken) {
      Log::info('Webhook de WhatsApp verificado correctamente');
      return response($challenge, 200);
    }

    Log::warning('Fallo en verificación de webhook de WhatsApp', [
      'mode' => $mode,
      'token' => $token,
      'expected_token' => $verifyToken
    ]);

    return response('Forbidden', 403);
  }

  /**
   * Obtener historial de conversación con un número
   */
  public function getConversation(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => [
        'required',
        'string',
        'regex:/^\+?[1-9]\d{1,14}$/',
      ],
      'limit' => [
        'nullable',
        'integer',
        'min:1',
        'max:100'
      ]
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    $phoneNumber = $this->normalizePhoneNumber($request->phone_number);
    $limit = $request->limit ?? 50;

    try {
      $conversation = Mensaje::getConversation($phoneNumber, $limit);

      return response()->json([
        'success' => true,
        'data' => [
          'phone_number' => $phoneNumber,
          'message_count' => $conversation->count(),
          'messages' => $conversation
        ]
      ]);
    } catch (\Exception $e) {
      Log::error('Error obteniendo conversación', [
        'error' => $e->getMessage(),
        'phone_number' => $phoneNumber
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error obteniendo conversación: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Obtener todas las conversaciones de WhatsApp agrupadas por número
   */
  public function getAllConversations(Request $request)
  {
    $limit = $request->get('limit', 20);
    $page = $request->get('page', 1);
    $search = $request->get('search');

    // Obtener conversaciones agrupadas por número de teléfono
    $query = Mensaje::select([
      'phone_number',
      DB::raw('MAX(created_at) as last_message_time'),
      DB::raw('COUNT(*) as message_count'),
      DB::raw('SUM(CASE WHEN direction = "received" THEN 1 ELSE 0 END) as received_count'),
      DB::raw('SUM(CASE WHEN direction = "sent" THEN 1 ELSE 0 END) as sent_count')
    ])
      ->whereNotNull('phone_number')
      ->groupBy('phone_number');

    // Aplicar filtro de búsqueda si existe
    if ($search) {
      $query->where('phone_number', 'LIKE', "%{$search}%");
    }

    $conversations = $query->orderBy('last_message_time', 'desc')
      ->paginate($limit, ['*'], 'page', $page);

    // Obtener el último mensaje de cada conversación
    $conversationsWithMessages = $conversations->items();
    $conversationsWithMessages = collect($conversationsWithMessages)->map(function ($conversation) {
      $lastMessage = Mensaje::where('phone_number', $conversation->phone_number)
        ->orderBy('created_at', 'desc')
        ->first();

      return [
        'phone_number' => $conversation->phone_number,
        'message_count' => $conversation->message_count,
        'received_count' => $conversation->received_count,
        'sent_count' => $conversation->sent_count,
        'last_message_time' => $conversation->last_message_time,
        'last_message' => $lastMessage ? [
          'id' => $lastMessage->id,
          'content' => $lastMessage->mensaje,
          'direction' => $lastMessage->direction,
          'message_type' => $lastMessage->message_type,
          'created_at' => $lastMessage->created_at
        ] : null,
        // Agregar información del contacto si existe
        'contact_info' => $this->getContactInfo($conversation->phone_number)
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
  }

  /**
   * Obtener información del contacto basada en el número de teléfono
   * Esta función puede ser expandida para buscar en otras tablas
   */
  private function getContactInfo($phoneNumber)
  {
    // Buscar si el número pertenece a un estudiante
    // Nota: Ajusta esto según la estructura de tu tabla de estudiantes
    $student = null;

    // Si tienes una tabla de estudiantes con campo de teléfono, puedes descomentar esto:
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

  /**
   * Eliminar conversación completa con un número de teléfono
   */
  public function deleteConversation(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => [
        'required',
        'string',
        'regex:/^\+?[1-9]\d{1,14}$/',
      ]
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    $phoneNumber = $this->normalizePhoneNumber($request->phone_number);

    try {
      $user = Auth::user();
      if (!$user) {
        return response()->json([
          'success' => false,
          'message' => 'Usuario no autenticado'
        ], 401);
      }

      // Eliminar todos los mensajes de esta conversación
      $deletedCount = Mensaje::where('phone_number', $phoneNumber)->delete();

      Log::info('Conversación de WhatsApp eliminada', [
        'phone_number' => $phoneNumber,
        'deleted_messages' => $deletedCount,
        'user_id' => $user->id
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Conversación eliminada exitosamente',
        'data' => [
          'phone_number' => $phoneNumber,
          'deleted_messages' => $deletedCount
        ]
      ]);
    } catch (\Exception $e) {
      Log::error('Error eliminando conversación de WhatsApp', [
        'error' => $e->getMessage(),
        'phone_number' => $phoneNumber
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error eliminando conversación: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Normalize phone number format for WhatsApp API
   */
  private function normalizePhoneNumber($phoneNumber)
  {
    // Remove all non-numeric characters except +
    $cleaned = preg_replace('/[^+\d]/', '', $phoneNumber);

    // If it doesn't start with +, add it
    if (!str_starts_with($cleaned, '+')) {
      $cleaned = '+' . $cleaned;
    }

    return $cleaned;
  }

  /**
   * Registrar mensaje de WhatsApp en el sistema de chat
   */
  private function logWhatsAppMessage($phoneNumber, $message, $messageType = 'text', $templateName = null, $isTemplate = false)
  {
    try {
      $user = Auth::user();
      if (!$user) {
        Log::warning('No hay usuario autenticado para registrar mensaje de WhatsApp');
        return;
      }

      // Crear o obtener session_id para WhatsApp
      $sessionId = ChatMessage::createSession($user->id, 'whatsapp_outbound', null);

      // Preparar el contenido del mensaje
      $content = $isTemplate
        ? "📱 Plantilla WhatsApp enviada: '{$templateName}' a {$phoneNumber}"
        : "📱 Mensaje WhatsApp enviado a {$phoneNumber}: {$message}";

      // Crear registro en chat_messages
      ChatMessage::create([
        'user_id' => $user->id,
        'role' => 'user', // El usuario que envía el mensaje
        'content' => $content,
        'conversation_type' => 'whatsapp_outbound',
        'related_id' => null,
        'session_id' => $sessionId,
        'metadata' => [
          'platform' => 'whatsapp',
          'phone_number' => $phoneNumber,
          'message_type' => $messageType,
          'template_name' => $templateName,
          'is_template' => $isTemplate,
          'sent_at' => now()->toISOString()
        ]
      ]);

      Log::info('Mensaje de WhatsApp registrado en chat', [
        'user_id' => $user->id,
        'phone_number' => $phoneNumber,
        'message_type' => $messageType
      ]);
    } catch (\Exception $e) {
      Log::error('Error al registrar mensaje de WhatsApp en chat', [
        'error' => $e->getMessage(),
        'phone_number' => $phoneNumber
      ]);
    }
  }

  /**
   * Almacenar mensaje en la tabla mensajes
   */
  private function storeMensaje($mensaje, $phoneNumber, $messageType = 'text', $direction = 'sent', $studentId = null)
  {
    try {
      $user = Auth::user();

      // Normalizar número de teléfono
      $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

      // Obtener o crear session_id para esta conversación
      $sessionId = Mensaje::getOrCreateSession($normalizedPhone, $user ? $user->id : null);

      // Si no se proporciona student_id, usar 0 como valor por defecto
      // En una implementación futura, podrías buscar el student_id basado en el número de teléfono
      $finalStudentId = $studentId ?? 0;

      Mensaje::create([
        'mensaje' => $mensaje,
        'student_id' => $finalStudentId,
        'phone_number' => $normalizedPhone,
        'direction' => $direction,
        'message_type' => $messageType,
        'session_id' => $sessionId,
        'user_id' => $user ? $user->id : null
      ]);

      Log::info('Mensaje almacenado en tabla mensajes', [
        'phone_number' => $normalizedPhone,
        'direction' => $direction,
        'message_type' => $messageType,
        'session_id' => $sessionId,
        'student_id' => $finalStudentId,
        'user_id' => $user ? $user->id : null,
        'mensaje_length' => strlen($mensaje)
      ]);
    } catch (\Exception $e) {
      Log::error('Error al almacenar mensaje en tabla mensajes', [
        'error' => $e->getMessage(),
        'phone_number' => $phoneNumber,
        'mensaje' => $mensaje,
        'direction' => $direction,
        'message_type' => $messageType
      ]);
    }
  }
}
