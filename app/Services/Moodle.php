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
     * Enviar una solicitud a la API de Moodle.
     * 
     * @param string $wsfunction Función de la API de Moodle a llamar
     * @param array $data Datos específicos para la función
     * @return array Respuesta formateada
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

            // Verificar si hay errores en la respuesta
            if ($statusCode !== 200 || isset($body['exception']) || isset($body['errorcode'])) {
                Log::error("Moodle API error", ['status' => $statusCode, 'response' => $body]);
                return [
                    'status' => 'error',
                    'message' => $body['message'] ?? 'Error desconocido',
                    'code' => $body['errorcode'] ?? $statusCode
                ];
            }

            // Verificar si hay warnings en la respuesta
            if (isset($body['warnings']) && !empty($body['warnings'])) {
                Log::info('Moodle API Response' . json_encode($body));

                // Si hay warnings pero la operación fue exitosa, devolver éxito con warnings
                return [
                    'status' => 'success',
                    'data' => $body,
                    'warnings' => $body['warnings']
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

            Log::info('Moodle API Response', ['wsfunction' => 'core_cohort_get_cohorts', 'status_code' => $response->getStatusCode(), 'body' => $body]);

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

    public function addUserToCohort($members)
    {
        Log::info('Adding user to cohorts', ['members' => $members]);

        try {
            $response = $this->client->post($this->url, [
                'form_params' => [
                    'wstoken' => $this->token,
                    'wsfunction' => 'core_cohort_add_cohort_members',
                    'moodlewsrestformat' => 'json',
                    'members' => $members
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            Log::info('Moodle API Response: ' . json_encode($body));

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
                'message' => 'Error adding user to cohort in Moodle FROM SERVICE: ' . $e->getMessage()
            ];
        }
    }

    public function deleteUser($userId)
    {
        Log::info('Deleting user from Moodle', ['moodle_user_id' => $userId]);

        $data = [
            'userids' => [$userId]
        ];

        return $this->sendRequest('core_user_delete_users', $data);
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

        if ($response['status'] === 'success' && !empty($response['data']) && isset($response['data'][0]['id'])) {
            return [
                'status' => 'success',
                'data' => $response['data'],
                'moodle_user_ids' => array_column($response['data'], 'id')
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
    /**
     * Crear cohortes en Moodle con formato correcto.
     * 
     * @param array $data Los datos del cohort a crear
     * @return array Respuesta de la API de Moodle
     */
    public function createCohorts(array $data)
    {
        // Asegurar que los datos tengan el formato correcto
        $formattedData = $this->formatCohorts($data);
        Log::info($formattedData);
        return $this->sendRequest('core_cohort_create_cohorts', $formattedData);
    }

    /**
     * Actualizar cohortes en Moodle con formato correcto.
     * 
     * @param array $data Los datos del cohort a actualizar
     * @return array Respuesta de la API de Moodle
     */
    public function updateCohorts(array $data)
    {
        // Asegurar que los datos tengan el formato correcto
        $formattedData = $this->formatCohorts($data);

        return $this->sendRequest('core_cohort_update_cohorts', $formattedData);
    }

    /**
     * Formatea los datos del cohort para asegurar que cumplan con la estructura requerida por la API de Moodle.
     * 
     * @param array $data Los datos del cohort a formatear
     * @return array Datos formateados
     */
    private function formatCohorts(array $data)
    {
        // Si los datos ya tienen la estructura correcta, devolverlos sin cambios
        if (isset($data['cohorts']) && is_array($data['cohorts'])) {
            foreach ($data['cohorts'] as $key => $cohort) {
                // Eliminar el campo contextid si existe, ya que causa errores
                if (isset($cohort['contextid'])) {
                    unset($data['cohorts'][$key]['contextid']);
                }

                // Eliminar campos vacíos que pueden causar errores
                if (isset($cohort['theme']) && empty($cohort['theme'])) {
                    unset($data['cohorts'][$key]['theme']);
                }

                if (isset($cohort['customfields']) && empty($cohort['customfields'])) {
                    unset($data['cohorts'][$key]['customfields']);
                }

                // Asegurar que todos los campos requeridos estén presentes
                $data['cohorts'][$key] = array_merge([
                    'name' => $cohort['name'] ?? '',
                    'idnumber' => $cohort['idnumber'] ?? '',
                    'description' => $cohort['description'] ?? '',
                    'descriptionformat' => 1,
                    'visible' => 1,
                ], $data['cohorts'][$key]);

                // Asegurar que categorytype esté correctamente formateado
                if (!isset($data['cohorts'][$key]['categorytype']) || !is_array($data['cohorts'][$key]['categorytype'])) {
                    $data['cohorts'][$key]['categorytype'] = [
                        'type' => 'system',
                        'value' => ''
                    ];
                }
            }
        } else {
            // Si los datos no tienen la estructura esperada, formatearlos
            $formattedData = ['cohorts' => []];

            foreach ($data as $key => $cohort) {
                $formattedCohort = [
                    'name' => $cohort['name'] ?? '',
                    'idnumber' => $cohort['idnumber'] ?? '',
                    'description' => $cohort['description'] ?? '',
                    'descriptionformat' => 1,
                    'visible' => 1,
                    'theme' => '',
                    'categorytype' => $cohort['categorytype'] ?? ['type' => 'system', 'value' => ''],
                    'customfields' => []
                ];

                $formattedData['cohorts'][] = $formattedCohort;
            }

            return $formattedData;
        }

        return $data;
    }

    /**
     * Obtener los cohorts de un usuario por su username.
     */
    public function getUserCohorts($userId)
    {
        Log::info('Getting user cohorts', ['user_id' => $userId]);

        $data = [
            'userid' => $userId
        ];

        return $this->sendRequest('core_cohort_get_user_cohorts', $data);
    }

    /**
     * Eliminar un usuario de un cohort específico.
     */
    public function removeUserFromCohort($userId, $cohortId)
    {
        Log::info('Removing user from cohort', ['user_id' => $userId, 'cohort_id' => $cohortId]);

        $data = [
            'members' => [
                [
                    'userid' => $userId,
                    'cohortid' => $cohortId
                ]
            ]
        ];

        return $this->sendRequest('core_cohort_delete_cohort_members', $data);
    }

    /**
     * Eliminar un usuario de todos sus cohorts.
     */
    public function removeUserFromAllCohorts($username)
    {
        Log::info('Removing user from all cohorts', ['username' => $username]);

        // Primero obtenemos el ID del usuario en Moodle
        $userResult = $this->getUserByUsername($username);

        if ($userResult['status'] !== 'success') {
            return [
                'status' => 'error',
                'message' => 'Usuario no encontrado en Moodle'
            ];
        }

        $userId = $userResult['data']['id'];

        // Obtenemos todos los cohorts del usuario
        $cohortsResult = $this->getUserCohorts($userId);

        if ($cohortsResult['status'] !== 'success') {
            return [
                'status' => 'error',
                'message' => 'Error al obtener los cohorts del usuario'
            ];
        }

        // Si no hay cohorts, retornamos éxito
        if (empty($cohortsResult['data']['cohorts'])) {
            return [
                'status' => 'success',
                'message' => 'El usuario no pertenece a ningún cohort'
            ];
        }

        $results = [];

        // Eliminamos al usuario de cada cohort
        foreach ($cohortsResult['data']['cohorts'] as $cohort) {
            $result = $this->removeUserFromCohort($userId, $cohort['id']);
            $results[] = $result;

            if ($result['status'] !== 'success') {
                Log::error('Error removing user from cohort', [
                    'username' => $username,
                    'cohort_id' => $cohort['id'],
                    'error' => $result['message']
                ]);
            }
        }

        return [
            'status' => 'success',
            'message' => 'Usuario eliminado de todos los cohorts',
            'details' => $results
        ];
    }
}
