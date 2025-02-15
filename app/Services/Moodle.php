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

    public function createCohorts(array $data)
    {
        $wsfunction = 'core_cohort_create_cohorts';
        $endpoint = $this->url . '?wstoken=' . urlencode($this->token)
            . '&wsfunction=' . urlencode($wsfunction)
            . '&moodlewsrestformat=json';

        try {
            $response = $this->client->post($endpoint, [
                'form_params' => $data
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
                    'status'  => 'error',
                    'message' => 'Error en la respuesta de Moodle: ' . ($body['message'] ?? 'Desconocido'),
                    'code'    => (int)$statusCode
                ];
            }

            if (isset($body['exception'])) {
                Log::error('Moodle API exception', ['exception' => $body]);
                return [
                    'status'  => 'error',
                    'message' => 'Excepción de Moodle: ' . $body['message'],
                    'code'    => (int)$body['errorcode']
                ];
            }

            // Extraer los IDs de los cohorts creados (si Moodle los retorna)
            $moodleCohortIds = [];
            if (is_array($body)) {
                foreach ($body as $cohort) {
                    if (isset($cohort['id'])) {
                        $moodleCohortIds[] = $cohort['id'];
                    }
                }
            }

            return [
                'status'            => 'success',
                'data'              => $body,
                'moodle_cohort_ids' => $moodleCohortIds
            ];
        } catch (RequestException $e) {
            Log::error('Moodle API RequestException', [
                'exception' => $e->getMessage(),
                'request'   => $e->getRequest(),
                'response'  => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return [
                'status'  => 'error',
                'message' => 'Error de conexión con Moodle: ' . $e->getMessage(),
                'code'    => (int)$e->getCode()
            ];
        } catch (\Exception $e) {
            Log::error('Moodle API Exception', ['exception' => $e->getMessage()]);
            return [
                'status'  => 'error',
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'code'    => (int)$e->getCode()
            ];
        }
    }
}
