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

    public function getCohortIdByName($cohortName)
    {
        Log::info('Cohort name: ' . $cohortName);

        try {
            $response = $this->client->get($this->url, [
                'query' => [
                    'wstoken' => $this->token,
                    'wsfunction' => 'core_cohort_get_cohorts',
                    'moodlewsrestformat' => 'json'
                ]
            ]);
    
            $body = json_decode($response->getBody(), true);

            // Log::info('Moodle API Response', ['wsfunction' => 'core_cohort_get_cohorts', 'status_code' => $response->getStatusCode(), 'body' => $body]);

            foreach ($body as $cohort) {
                if ($cohort['name'] === $cohortName) {
                    return $cohort['id'];
                }
            }
    
            return null;
        } catch (\Exception $e) {
            Log::error('Moodle API Exception', ['exception' => $e->getMessage()]);
            return null;
        }
    }
    
    public function addUserToCohort($username, $cohortId) 
    {
        Log::info('Adding user to cohort', ['username' => $username, 'cohort_id' => $cohortId]);
    
        try {
            $response = $this->client->post($this->url, [
                'form_params' => [
                    'wstoken' => $this->token,
                    'wsfunction' => 'core_cohort_add_cohort_members',
                    'moodlewsrestformat' => 'json',
                    'members' => [
                        [
                            'cohorttype' => [
                                'type' => 'id',
                                'value' => $cohortId
                            ],
                            'usertype' => [
                                'type' => 'username',
                                'value' => $username
                            ]
                        ]
                    ]
                ]
            ]);
    
            $body = json_decode($response->getBody(), true);
            
            Log::info('Moodle API Response' . json_encode($body));

            if (isset($body['exception'])) {
                return [
                    'status' => 'error',
                    'message' => $body['message']
                ];
            }
    
            return [
                'status' => 'success',
                'data' => $body
            ];
        } catch (\Exception $e) {
            Log::error('Moodle API Exception', ['exception' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => 'Error adding user to cohort in Moodle'
            ];
        }
    }
    
    public function getUserByUsername($username)
    {
    
        $data = [
            'criteria' => [
                [
                    'key' => 'username',
                    'value' => $username
                ]
            ]
        ];
    
        $response = $this->sendRequest('core_user_get_users', $data);
    
        if ($response['status'] === 'success' && isset($response['data']['users'][0])) {
            return [
                'status' => 'success',
                'data' => $response['data']['users'][0]
            ];
        }
    
        return [
            'status' => 'error',
            'message' => 'User not found or error occurred',
            'response' => $response
        ];
    }
    /**
     * Crear usuarios en Moodle.
     */

    public function updateUser(array $users)
    {
        $response = $this->sendRequest('core_user_update_users', $this->formatUsers($users));

        if (is_array($response) && !empty($response) && isset($response[0]['id'])) {
            return [
                'status' => 'success',
                'data' => $response,
                'moodle_user_ids' => array_column($response, 'id')
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Error updating user in Moodle',
            'response' => $response
        ];
    }

    public function createUser(array $users)
    {
        $response = $this->sendRequest('core_user_create_users', $this->formatUsers($users));
    
        if (is_array($response) && !empty($response) && isset($response[0]['id'])) {
            return [
                'status' => 'success',
                'data' => $response,
                'moodle_user_ids' => array_column($response, 'id')
            ];
        }
    
        return [
            'status' => 'error',
            'message' => 'Error creating user in Moodle',
            'response' => $response
        ];
    }
    
    /**
     * Crear cohortes en Moodle.
     */
    public function createCohorts(array $data)
    {
        return $this->sendRequest('core_cohort_create_cohorts', ['cohorts' => $data]);
    }
}
