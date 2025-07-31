<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
}