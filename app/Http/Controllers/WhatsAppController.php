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
use App\Models\Context;
use App\Services\MCPServerService;
use App\Services\AIFunctionService;

class WhatsAppController extends Controller
{
  private $whatsappToken;
  private $phoneNumberId;
  private $apiUrl;
  private MCPServerService $mcpServer;
  private AIFunctionService $aiFunctionService;

  public function __construct(MCPServerService $mcpServer, AIFunctionService $aiFunctionService)
  {
    $this->whatsappToken = env('WHATSAPP_TOKEN');
    $this->phoneNumberId = env('PHONE_NUMBER_ID');
    $this->apiUrl = "https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages";
    $this->mcpServer = $mcpServer;
    $this->aiFunctionService = $aiFunctionService;
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

      // Generar y enviar respuesta automática con IA
      if ($messageType === 'text' && !empty($content)) {
        $autoResponseSent = $this->generateAutoResponse($phoneNumber, $content, $messageType);
        
        if ($autoResponseSent) {
          Log::info('Respuesta automática enviada exitosamente', [
            'phone_number' => $phoneNumber
          ]);
        }
      }

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
   * Probar respuesta automática con MCP Server (para testing)
   */
  public function testAutoResponseMCP(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
      'message' => 'required|string|min:1|max:1000',
      'send_response' => 'boolean'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $phoneNumber = $this->normalizePhoneNumber($request->phone_number);
      $message = $request->message;
      $sendResponse = $request->get('send_response', false);

      // Obtener historial de conversación
      $conversationHistory = $this->getWhatsAppConversationHistory($phoneNumber, 10);
      
      // Usar el servicio MCP para generar respuesta
      $response = $this->aiFunctionService->processWhatsAppMessage(
        $phoneNumber,
        $message,
        $conversationHistory->toArray()
      );

      if (!$response['success']) {
        return response()->json([
          'success' => false,
          'message' => 'Error generando respuesta MCP: ' . $response['error']
        ], 500);
      }

      $generatedResponse = $response['response_message'];

      // Enviar respuesta si se solicita
      if ($sendResponse) {
        $sent = $this->sendAutoGeneratedMessage($phoneNumber, $generatedResponse);
        
        if (!$sent) {
          return response()->json([
            'success' => false,
            'message' => 'Respuesta generada pero no se pudo enviar'
          ], 500);
        }
      }

      return response()->json([
        'success' => true,
        'data' => [
          'phone_number' => $phoneNumber,
          'incoming_message' => $message,
          'generated_response' => $generatedResponse,
          'sent' => $sendResponse,
          'conversation_length' => $conversationHistory->count(),
          'student_info' => $response['student_info'],
          'functions_called' => $response['functions_called'],
          'tokens_used' => $response['tokens_used']
        ]
      ]);

    } catch (\Exception $e) {
      Log::error('Error en test de respuesta automática MCP', [
        'error' => $e->getMessage(),
        'phone_number' => $request->phone_number
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Ejecutar una función MCP específica
   */
  public function executeMCPFunction(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'function_name' => 'required|string',
      'parameters' => 'required|array'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $functionName = $request->function_name;
      $parameters = $request->parameters;

      $result = $this->mcpServer->executeFunction($functionName, $parameters);

      return response()->json([
        'success' => true,
        'function_name' => $functionName,
        'parameters' => $parameters,
        'result' => $result
      ]);

    } catch (\Exception $e) {
      Log::error('Error ejecutando función MCP', [
        'function' => $request->function_name,
        'parameters' => $request->parameters,
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error ejecutando función: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Obtener lista de funciones MCP disponibles
   */
  public function getMCPFunctions()
  {
    try {
      $functions = $this->mcpServer->getAvailableFunctions();

      return response()->json([
        'success' => true,
        'data' => [
          'total_functions' => count($functions),
          'functions' => $functions
        ]
      ]);

    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error obteniendo funciones MCP: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Buscar estudiante por matrícula usando MCP
   */
  public function getStudentByMatricula(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'matricula' => 'required|string'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Matrícula es requerida',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $result = $this->mcpServer->executeFunction('get_student_by_id', [
        'id' => $request->matricula
      ]);

      return response()->json($result);

    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error buscando estudiante: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Obtener calificaciones de estudiante por matrícula usando MCP
   */
  public function getStudentGradesByMatricula(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'matricula' => 'required|string'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Matrícula es requerida',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $result = $this->mcpServer->executeFunction('get_student_grades', [
        'student_id' => (int) $request->matricula
      ]);

      return response()->json($result);

    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error obteniendo calificaciones: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Obtener calificaciones de estudiante por teléfono usando MCP
   */
  public function getStudentGradesByPhone(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => 'required|string'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Número de teléfono es requerido',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $result = $this->mcpServer->executeFunction('get_student_grades_by_phone', [
        'phone_number' => $request->phone_number
      ]);

      return response()->json($result);

    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error obteniendo calificaciones: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Test de formato de reporte completo (pagos + calificaciones)
   */
  public function testCompleteReport(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'matricula' => 'required|string'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Matrícula es requerida',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $studentId = (int) $request->matricula;

      // Obtener información del estudiante
      $studentResult = $this->mcpServer->executeFunction('get_student_by_id', [
        'id' => (string) $studentId
      ]);

      if (!$studentResult['success']) {
        return response()->json($studentResult, 404);
      }

      $studentData = $studentResult['data'];

      // Obtener pagos
      $paymentsResult = $this->mcpServer->executeFunction('get_student_payments', [
        'student_id' => $studentId,
        'limit' => 5
      ]);

      // Obtener calificaciones
      $gradesResult = $this->mcpServer->executeFunction('get_student_grades', [
        'student_id' => $studentId
      ]);

      // Generar reporte completo formateado
      $formattedReport = $this->mcpServer->formatCompleteStudentReport(
        $studentData,
        $paymentsResult,
        $gradesResult
      );

      return response()->json([
        'success' => true,
        'data' => [
          'student' => $studentData,
          'formatted_report' => $formattedReport,
          'raw_data' => [
            'payments' => $paymentsResult,
            'grades' => $gradesResult
          ]
        ]
      ]);

    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error generando reporte: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Probar respuesta en español (endpoint de testing)
   */
  public function testSpanishResponse(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'phone_number' => 'required|string',
      'message' => 'required|string'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $phoneNumber = $this->normalizePhoneNumber($request->phone_number);
      $message = $request->message;

      // Test directo con instrucciones reforzadas en español
      $systemMessage = "Eres un asistente de WhatsApp para una institución educativa en México. ";
      $systemMessage .= "INSTRUCCIÓN CRÍTICA: Debes responder ÚNICAMENTE en español mexicano. ";
      $systemMessage .= "Está PROHIBIDO usar inglés, incluso palabras sueltas. ";
      $systemMessage .= "Usa expresiones mexicanas cuando sea apropiado. ";
      $systemMessage .= "Sé amigable, profesional y conciso. ";
      $systemMessage .= "Máximo 2-3 párrafos. Usa emojis apropiados. ";

      // Buscar estudiante
      $studentResult = $this->mcpServer->executeFunction('get_student_by_phone', [
        'phone_number' => $phoneNumber
      ]);

      if ($studentResult['success']) {
        $student = $studentResult['data'];
        $systemMessage .= "El usuario es {$student['name']} con matrícula {$student['matricula']}. ";
        $systemMessage .= "Salúdalo por su nombre de manera amigable. ";
      }

      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . config('services.openai.api_key'),
        'Content-Type' => 'application/json'
      ])->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o-mini',
        'messages' => [
          ['role' => 'system', 'content' => $systemMessage],
          ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 300,
        'temperature' => 0.7,
        'presence_penalty' => 0.1,
        'frequency_penalty' => 0.1
      ]);

      if ($response->successful()) {
        $data = $response->json();
        $responseMessage = $data['choices'][0]['message']['content'];

        return response()->json([
          'success' => true,
          'data' => [
            'phone_number' => $phoneNumber,
            'incoming_message' => $message,
            'response_message' => $responseMessage,
            'student_found' => $studentResult['success'],
            'student_info' => $studentResult['success'] ? $studentResult['data'] : null,
            'system_message_used' => $systemMessage,
            'language_check' => [
              'contains_english' => $this->containsEnglish($responseMessage),
              'is_spanish' => $this->isSpanish($responseMessage)
            ]
          ]
        ]);
      }

      return response()->json([
        'success' => false,
        'message' => 'Error al generar respuesta'
      ], 500);

    } catch (\Exception $e) {
      Log::error('Error en test de respuesta en español', [
        'error' => $e->getMessage(),
        'phone_number' => $request->phone_number
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Verificar si el texto contiene palabras en inglés
   */
  private function containsEnglish($text): bool
  {
    $englishWords = [
      'hello', 'hi', 'how', 'are', 'you', 'what', 'where', 'when', 'why', 'how',
      'good', 'bad', 'yes', 'no', 'please', 'thank', 'thanks', 'welcome',
      'student', 'payment', 'grade', 'schedule', 'attendance', 'profile',
      'information', 'help', 'support', 'contact', 'phone', 'email'
    ];

    $textLower = strtolower($text);
    foreach ($englishWords as $word) {
      if (strpos($textLower, $word) !== false) {
        return true;
      }
    }
    return false;
  }

  /**
   * Verificar si el texto está principalmente en español
   */
  private function isSpanish($text): bool
  {
    $spanishWords = [
      'hola', 'cómo', 'estás', 'qué', 'dónde', 'cuándo', 'por qué', 'cómo',
      'bueno', 'malo', 'sí', 'no', 'por favor', 'gracias', 'de nada',
      'estudiante', 'pago', 'calificación', 'horario', 'asistencia', 'perfil',
      'información', 'ayuda', 'apoyo', 'contacto', 'teléfono', 'correo',
      'matricula', 'matrícula', 'universidad', 'escuela', 'instituto'
    ];

    $textLower = strtolower($text);
    $spanishCount = 0;
    foreach ($spanishWords as $word) {
      if (strpos($textLower, $word) !== false) {
        $spanishCount++;
      }
    }
    return $spanishCount > 0;
  }

  /**
   * Configurar respuestas automáticas de WhatsApp
   */
  public function configureAutoResponse(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'enabled' => 'required|boolean',
      'instructions' => 'nullable|string|max:2000',
      'excluded_numbers' => 'nullable|array',
      'excluded_numbers.*' => 'string|regex:/^\+?[1-9]\d{1,14}$/',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => 'Datos de entrada inválidos',
        'errors' => $validator->errors()
      ], 422);
    }

    try {
      $user = Auth::user();
      if (!$user) {
        return response()->json([
          'success' => false,
          'message' => 'Usuario no autenticado'
        ], 401);
      }

      // Guardar configuración en caché o base de datos
      $config = [
        'enabled' => $request->enabled,
        'instructions' => $request->instructions ?? '',
        'excluded_numbers' => $request->excluded_numbers ?? [],
        'updated_by' => $user->id,
        'updated_at' => now()
      ];

      // Guardar en caché por ahora (en producción podrías usar una tabla de configuración)
      cache()->put('whatsapp_auto_response_config', $config, now()->addDays(30));

      Log::info('Configuración de respuestas automáticas actualizada', [
        'enabled' => $request->enabled,
        'updated_by' => $user->id
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Configuración actualizada exitosamente',
        'config' => $config
      ]);

    } catch (\Exception $e) {
      Log::error('Error configurando respuestas automáticas', [
        'error' => $e->getMessage(),
        'user_id' => $user->id ?? null
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error actualizando configuración: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Obtener configuración actual de respuestas automáticas
   */
  public function getAutoResponseConfig()
  {
    $defaultEnabled = config('services.whatsapp.auto_response.enabled', false);
    $defaultInstructions = config('services.whatsapp.auto_response.default_instructions', '');
    
    $config = cache()->get('whatsapp_auto_response_config', [
      'enabled' => $defaultEnabled,
      'instructions' => $defaultInstructions,
      'excluded_numbers' => [],
      'updated_by' => null,
      'updated_at' => null
    ]);

    return response()->json([
      'success' => true,
      'config' => $config
    ]);
  }

  /**
   * Resetear configuración a valores por defecto
   */
  public function resetAutoResponseConfig()
  {
    try {
      $user = Auth::user();
      if (!$user) {
        return response()->json([
          'success' => false,
          'message' => 'Usuario no autenticado'
        ], 401);
      }

      $defaultEnabled = config('services.whatsapp.auto_response.enabled', false);
      $defaultInstructions = config('services.whatsapp.auto_response.default_instructions', '');

      $config = [
        'enabled' => $defaultEnabled,
        'instructions' => $defaultInstructions,
        'excluded_numbers' => [],
        'updated_by' => $user->id,
        'updated_at' => now()
      ];

      cache()->put('whatsapp_auto_response_config', $config, now()->addDays(30));

      Log::info('Configuración de respuestas automáticas reseteada', [
        'reset_by' => $user->id,
        'default_enabled' => $defaultEnabled
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Configuración reseteada a valores por defecto',
        'config' => $config
      ]);

    } catch (\Exception $e) {
      Log::error('Error reseteando configuración de respuestas automáticas', [
        'error' => $e->getMessage(),
        'user_id' => $user->id ?? null
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error reseteando configuración: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Verificar si las respuestas automáticas están habilitadas para un número
   */
  private function isAutoResponseEnabled($phoneNumber = null)
  {
    $defaultEnabled = config('services.whatsapp.auto_response.enabled', false);
    $config = cache()->get('whatsapp_auto_response_config', ['enabled' => $defaultEnabled]);
    
    if (!$config['enabled']) {
      return false;
    }

    // Verificar si el número está en la lista de excluidos
    if ($phoneNumber && isset($config['excluded_numbers'])) {
      $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
      
      foreach ($config['excluded_numbers'] as $excludedNumber) {
        if ($this->normalizePhoneNumber($excludedNumber) === $normalizedPhone) {
          return false;
        }
      }
    }

    return true;
  }

  /**
   * Generar respuesta automática con IA para un mensaje de WhatsApp recibido
   */
  /**
   * Generar respuesta automática con IA usando el MCP Server
   */
  public function generateAutoResponse($phoneNumber, $incomingMessage, $messageType = 'text')
  {
    try {

      // Obtener historial de conversación
      $conversationHistory = $this->getWhatsAppConversationHistory($phoneNumber, 10);
      
      // Usar el nuevo servicio de IA con funciones MCP
      $response = $this->aiFunctionService->processWhatsAppMessage(
        $phoneNumber,
        $incomingMessage,
        $conversationHistory->toArray()
      );

      if (!$response['success']) {
        Log::error('Error generando respuesta automática con MCP', [
          'phone_number' => $phoneNumber,
          'error' => $response['error']
        ]);
        
        // Fallback a respuesta simple
        $simpleResponse = $this->aiFunctionService->generateSimpleResponse(
          $phoneNumber,
          $incomingMessage,
          $conversationHistory->toArray()
        );
        
        if ($simpleResponse['success']) {
          $responseMessage = $simpleResponse['response_message'];
        } else {
          return false;
        }
      } else {
        $responseMessage = $response['response_message'];
        
        // Log de funciones ejecutadas
        if (!empty($response['functions_called'])) {
          Log::info('Funciones MCP ejecutadas en respuesta automática', [
            'phone_number' => $phoneNumber,
            'functions' => array_column($response['functions_called'], 'function'),
            'student_info' => $response['student_info'] ? 'Encontrado' : 'No encontrado'
          ]);
        }
      }

      // Enviar la respuesta generada
      $sent = $this->sendAutoGeneratedMessage($phoneNumber, $responseMessage);

      if ($sent) {
        Log::info('Respuesta automática MCP enviada', [
          'phone_number' => $phoneNumber,
          'response_length' => strlen($responseMessage),
          'functions_used' => !empty($response['functions_called']) ? count($response['functions_called']) : 0,
          'student_identified' => isset($response['student_info']),
          'tokens_used' => $response['tokens_used'] ?? null
        ]);

        // Registrar la respuesta en el sistema de chat
        $this->logWhatsAppAutoResponse($phoneNumber, $incomingMessage, $responseMessage, $response);
        
        return true;
      }

      return false;

    } catch (\Exception $e) {
      Log::error('Error en respuesta automática MCP de WhatsApp', [
        'phone_number' => $phoneNumber,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return false;
    }
  }

  /**
   * Obtener historial de conversación de WhatsApp para contexto de IA
   */
  private function getWhatsAppConversationHistory($phoneNumber, $limit = 10)
  {
    $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
    
    return Mensaje::where('phone_number', $normalizedPhone)
      ->orderBy('created_at', 'desc')
      ->limit($limit)
      ->get()
      ->reverse()
      ->values();
  }

  /**
   * Formatear conversación para enviar a la IA
   */
  private function formatConversationForAI($messages)
  {
    return $messages->map(function ($message) {
      $role = $message->direction === 'received' ? 'user' : 'assistant';
      $content = $message->mensaje;
      
      // Agregar contexto del tipo de mensaje si no es texto
      if ($message->message_type !== 'text') {
        $content = "[{$message->message_type}] {$content}";
      }
      
      return [
        'role' => $role,
        'content' => $content
      ];
    })->toArray();
  }

  /**
   * Construir mensaje del sistema específico para WhatsApp
   */
  private function buildWhatsAppSystemMessage()
  {
    // Obtener contextos activos del sistema
    $activeContexts = Context::where('is_active', true)->get();
    
    $baseInstructions = "Eres un asistente de WhatsApp para una institución educativa en México. ";
    $baseInstructions .= "IMPORTANTE: SIEMPRE responde en ESPAÑOL. Nunca uses inglés. ";
    $baseInstructions .= "Responde de manera amigable, profesional y concisa. ";
    $baseInstructions .= "Mantén las respuestas cortas ya que es WhatsApp (máximo 2-3 párrafos). ";
    $baseInstructions .= "Si necesitas información específica del estudiante, pide que se comuniquen por otros medios. ";
    $baseInstructions .= "Siempre sé útil y orientativo. ";
    $baseInstructions .= "Usa emojis ocasionalmente para hacer la conversación más amigable.";

    // Si hay contextos activos, usarlos como instrucciones principales
    if ($activeContexts->isNotEmpty()) {
      $contextInstructions = $activeContexts->map(function ($context) {
        return "{$context->name}: {$context->instructions}";
      })->join('\n\n');
      
      return $baseInstructions . "\n\nInstrucciones específicas:\n\n" . $contextInstructions;
    }

    return $baseInstructions;
  }

  /**
   * Enviar mensaje generado automáticamente
   */
  private function sendAutoGeneratedMessage($phoneNumber, $message)
  {
    $messageData = [
      'messaging_product' => 'whatsapp',
      'to' => $phoneNumber,
      'type' => 'text',
      'text' => [
        'preview_url' => false,
        'body' => $message
      ]
    ];

    try {
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->whatsappToken,
        'Content-Type' => 'application/json'
      ])->post($this->apiUrl, $messageData);

      if ($response->successful()) {
        // Almacenar en la tabla mensajes
        $this->storeMensaje($message, $phoneNumber, 'text', 'sent');
        return true;
      }

      Log::error('Error enviando respuesta automática', [
        'phone_number' => $phoneNumber,
        'status' => $response->status(),
        'response' => $response->json()
      ]);

      return false;
    } catch (\Exception $e) {
      Log::error('Excepción enviando respuesta automática', [
        'phone_number' => $phoneNumber,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Registrar respuesta automática en el sistema de chat
   */
  private function logWhatsAppAutoResponse($phoneNumber, $incomingMessage, $responseMessage, $mcpResponse = null)
  {
    try {
      // Crear session_id específico para respuestas automáticas de WhatsApp
      $sessionId = ChatMessage::createSession(1, 'whatsapp_inbound', null); // Usar user_id 1 como sistema

      // Registrar mensaje recibido
      ChatMessage::create([
        'user_id' => 1, // Usuario del sistema
        'role' => 'user',
        'content' => "📱 WhatsApp recibido de {$phoneNumber}: {$incomingMessage}",
        'conversation_type' => 'whatsapp_inbound',
        'session_id' => $sessionId,
        'metadata' => [
          'platform' => 'whatsapp',
          'phone_number' => $phoneNumber,
          'auto_response' => true,
          'incoming_message' => true,
          'mcp_enabled' => true
        ]
      ]);

      // Preparar metadata de la respuesta
      $responseMetadata = [
        'platform' => 'whatsapp',
        'phone_number' => $phoneNumber,
        'auto_response' => true,
        'outgoing_message' => true,
        'model' => 'gpt-4o-mini',
        'mcp_enabled' => true
      ];

      // Agregar información MCP si está disponible
      if ($mcpResponse && isset($mcpResponse['functions_called'])) {
        $responseMetadata['mcp_functions_called'] = array_column($mcpResponse['functions_called'], 'function');
        $responseMetadata['mcp_functions_count'] = count($mcpResponse['functions_called']);
        $responseMetadata['student_identified'] = isset($mcpResponse['student_info']);
        $responseMetadata['tokens_used'] = $mcpResponse['tokens_used'] ?? null;
        
        if (isset($mcpResponse['student_info'])) {
          $responseMetadata['student_matricula'] = $mcpResponse['student_info']['matricula'] ?? null;
          $responseMetadata['student_name'] = $mcpResponse['student_info']['name'] ?? null;
        }
      }

      // Registrar respuesta automática
      ChatMessage::create([
        'user_id' => 1, // Usuario del sistema
        'role' => 'assistant',
        'content' => $responseMessage,
        'conversation_type' => 'whatsapp_inbound',
        'session_id' => $sessionId,
        'metadata' => $responseMetadata
      ]);

    } catch (\Exception $e) {
      Log::error('Error registrando respuesta automática MCP en chat', [
        'error' => $e->getMessage(),
        'phone_number' => $phoneNumber
      ]);
    }
  }

  /**
   * Enviar mensaje a OpenAI específico para WhatsApp
   */
  private function sendToOpenAIForWhatsApp($systemMessage, $conversationHistory, $userMessage)
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
      ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o-mini',
        'messages' => $messages,
        'max_tokens' => 300, // Limitar para WhatsApp
        'temperature' => 0.7
      ]);

      if (!$response->successful()) {
        Log::error('Error de OpenAI API para WhatsApp', [
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
        'content' => $data['choices'][0]['message']['content'] ?? 'No se pudo generar respuesta',
        'model' => $data['model'] ?? 'gpt-4o-mini',
        'tokens_used' => $data['usage']['total_tokens'] ?? null
      ];

    } catch (\Exception $e) {
      Log::error('Error al conectar con OpenAI para WhatsApp', [
        'error' => $e->getMessage()
      ]);

      return [
        'success' => false,
        'error' => 'Error de conexión con OpenAI'
      ];
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
