<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Moodle
{
    protected Client $client;
    protected string $token;
    protected string $url;

    public function __construct()
    {
        $this->client = new Client(['http_errors' => false]);
        $this->token = env('MOODLE_TOKEN');
        $this->url = env('MOODLE_URL');
    }

    /**
     * Método genérico para realizar peticiones a la API de Moodle.
     */
    private function sendRequest(string $wsfunction, array $data = [])
    {
        try {
            $response = $this->client->post($this->url, [
                'form_params' => [
                    'wstoken' => $this->token,
                    'wsfunction' => $wsfunction,
                    'moodlewsrestformat' => 'json'
                ] + $data
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            Log::info("Moodle API Response", [
                'wsfunction' => $wsfunction,
                'status_code' => $statusCode,
                'body' => $body
            ]);

            if ($statusCode !== 200 || isset($body['exception'])) {
                Log::error("Moodle API error", ['status' => $statusCode, 'response' => $body]);
                return [
                    'status' => 'error',
                    'message' => $body['message'] ?? 'Error desconocido',
                    'code' => $body['errorcode'] ?? $statusCode
                ];
            }

            return [
                'status' => 'success',
                'data' => $body
            ];
        } catch (RequestException $e) {
            return $this->handleException($e);
        } catch (\Exception $e) {
            Log::error("Moodle API Exception", ['exception' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Manejo de excepciones para peticiones fallidas.
     */
    private function handleException($e)
    {
        Log::error("Moodle API RequestException", [
            'exception' => $e->getMessage(),
            'request' => $e->getRequest(),
            'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
        ]);

        return [
            'status' => 'error',
            'message' => 'Error de conexión con Moodle: ' . $e->getMessage(),
            'code' => $e->getCode()
        ];
    }

    /**
     * Formatear usuarios para la API de Moodle.
     */
    private function formatUsers(array $users): array
    {
        $formattedUsers = [];
        foreach ($users as $index => $user) {
            foreach ($user as $key => $value) {
                $formattedUsers["users[{$index}][{$key}]"] = is_string($value) ? trim($value) : $value;
            }
        }
        return $formattedUsers;
    }

    /**
     * Crear usuarios en Moodle.
     */
    public function createUser(array $users)
    {
        return $this->sendRequest('core_user_create_users', $this->formatUsers($users));
    }

    /**
     * Crear cohortes en Moodle.
     */
    public function createCohorts(array $data)
    {
        return $this->sendRequest('core_cohort_create_cohorts', ['cohorts' => $data]);
    }

    
}
