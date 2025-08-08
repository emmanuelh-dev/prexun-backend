<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatMessage;

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
}