<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Moodle
{
    protected $client;
    protected $token;
    protected $url;

    public function __construct()
    {
        $this->client = new Client([
            'http_errors' => false,
        ]);
        $this->token = env('MOODLE_TOKEN');
        $this->url = env('MOODLE_URL');
    }
    private function formatUsers($users)
    {
        $formattedUsers = [];
        foreach ($users as $index => $user) {
            foreach ($user as $key => $value) {
                // Aseguramos que los valores sean cadenas y eliminamos espacios extras
                $formattedUsers["users[{$index}][{$key}]"] = is_string($value) ? trim($value) : $value;
            }
        }
        return $formattedUsers;
    }


    public function createUser($users)
    {
        try {
            $formattedUsers = $this->formatUsers($users);

            Log::info('Moodle API Request', [
                'url' => $this->url,
                'users' => $formattedUsers
            ]);

            $response = $this->client->post($this->url, [
                'form_params' => [
                    'wstoken' => $this->token,
                    'wsfunction' => 'core_user_create_users',
                    'moodlewsrestformat' => 'json',
                ] + $formattedUsers
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            Log::info('Moodle API Response', [
                'status_code' => $statusCode,
                'body' => $body
            ]);

            if ($statusCode !== 200) {
                Log::error('Moodle API error', ['status' => $statusCode, 'response' => $body]);
                return [
                    'status' => 'error',
                    'message' => 'Error en la respuesta de Moodle: ' . ($body['message'] ?? 'Desconocido'),
                    'code' => $statusCode
                ];
            }

            if (isset($body['exception'])) {
                Log::error('Moodle API exception', ['exception' => $body]);
                return [
                    'status' => 'error',
                    'message' => 'Excepción de Moodle: ' . $body['message'],
                    'code' => $body['errorcode'] ?? 'unknown'
                ];
            }

            if (empty($body) || !isset($body[0]['id'])) {
                Log::warning('Moodle API unexpected response', ['body' => $body]);
                return [
                    'status' => 'warning',
                    'message' => 'Respuesta inesperada de Moodle',
                    'code' => 'unexpected_response'
                ];
            }

            return [
                'status' => 'success',
                'data' => $body,
                'moodle_user_ids' => array_column($body, 'id')
            ];
        } catch (RequestException $e) {
            Log::error('Moodle API RequestException', [
                'exception' => $e->getMessage(),
                'request' => $e->getRequest(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return [
                'status' => 'error',
                'message' => 'Error de conexión con Moodle: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];
        } catch (\Exception $e) {
            Log::error('Moodle API Exception', ['exception' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
    private function formatCohorts($cohorts)
    {
        // Verificar si los cohorts ya están formateados
        if (isset($cohorts['cohorts'])) {
            return $cohorts; // Ya está en el formato correcto
        }
    
        $formattedCohorts = [];
    
        foreach ($cohorts as $cohort) {
            // Verificar si es un array o un objeto y si la clave existe
            $categoryId = is_object($cohort) 
                ? ($cohort->period->id ?? null) 
                : ($cohort['period_id'] ?? null);
    
            $name = is_object($cohort) ? ($cohort->name ?? '') : ($cohort['name'] ?? '');
            $id = is_object($cohort) ? ($cohort->id ?? '') : ($cohort['id'] ?? '');
    
            $formattedCohorts[] = [
                'categorytype' => [
                    'type' => 'string',
                    'value' => (string)$categoryId
                ],
                'name' => (string)$name,
                'idnumber' => (string)$id,
                'description' => 'Descripción del cohorte ' . $name,
                'descriptionformat' => 1,
                'visible' => 1,
                'theme' => '',
                'customfields' => []
            ];
        }
    
        return [
            'cohorts' => $formattedCohorts
        ];
    }
    

    public function createCohorts($cohorts)
    {Log::info('Datos originales recibidos para Moodle:', ['cohorts' => $cohorts]);
        try {
            $formattedData = $this->formatCohorts($cohorts);
            
            Log::info('Formatted cohorts data for Moodle:', [
                'formatted_count' => count($formattedData['cohorts'])
            ]);

            $requestData = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_cohort_create_cohorts',
                'moodlewsrestformat' => 'json',
                'cohorts' => $formattedData['cohorts']
            ];

            $response = $this->client->post($this->url, [
                'form_params' => $requestData
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['exception'])) {
                throw new \Exception($body['message'] ?? 'Unknown Moodle API error');
            }

            return [
                'status' => 'success',
                'data' => $body,
                'moodle_cohort_ids' => array_column($body, 'id')
            ];

        } catch (RequestException $e) {
            Log::error('Moodle API Request Exception:', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to communicate with Moodle: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];

        } catch (\Exception $e) {
            Log::error('Unexpected error in createCohorts:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => 'Unexpected error: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
}