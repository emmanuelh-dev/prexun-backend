<?php

namespace App\Services\Moodle;

use Illuminate\Support\Facades\Log;

class MoodleCohortService extends BaseMoodleService
{
    /**
     * Obtener cohort por nombre.
     */
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

            Log::info('Moodle API Response', [
                'wsfunction' => 'core_cohort_get_cohorts',
                'status_code' => $response->getStatusCode(),
                'body' => $body
            ]);

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

    /**
     * Crear cohortes en Moodle.
     */
    public function createCohorts(array $data)
    {
        $formattedData = $this->formatCohorts($data);
        Log::info($formattedData);
        return $this->sendRequest('core_cohort_create_cohorts', $formattedData);
    }

    /**
     * Actualizar cohortes en Moodle.
     */
    public function updateCohorts(array $data)
    {
        $formattedData = $this->formatCohorts($data);
        return $this->sendRequest('core_cohort_update_cohorts', $formattedData);
    }    /**
     * Agregar usuario a cohort.
     */
    public function addUserToCohort($members)
    {
        Log::info('Adding user to cohorts', ['members' => $members]);

        // Verificar que todos los members tengan el formato correcto con cohorttype y usertype
        $formattedMembers = [];
        
        foreach ($members as $member) {
            if (isset($member['cohorttype']) && isset($member['usertype'])) {
                // Formato correcto - usar directamente
                $formattedMembers[] = $member;
            } elseif (isset($member['userid']) && isset($member['cohortid'])) {
                // Formato obsoleto - NO hacer conversión automática ya que userid puede ser Moodle ID o student ID
                Log::error('Deprecated userid/cohortid format in addUserToCohort', [
                    'member' => $member,
                    'error' => 'userid/cohortid format is ambiguous - use cohorttype/usertype format instead'
                ]);
                
                return [
                    'status' => 'error',
                    'message' => 'Formato obsoleto userid/cohortid no permitido en addUserToCohort. Use cohorttype/usertype con username del estudiante.'
                ];
            } else {
                Log::error('Invalid member format', ['member' => $member]);
                return [
                    'status' => 'error',
                    'message' => 'Formato de miembro inválido: debe contener cohorttype/usertype'
                ];
            }
        }

        $data = [
            'members' => $formattedMembers
        ];

        return $this->sendRequest('core_cohort_add_cohort_members', $data);
    }

    /**
     * Eliminar usuario de un cohort específico.
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
     * Eliminar múltiples usuarios de cohorts.
     * Esta es la nueva función que solicitaste.
     */
    public function removeUsersFromCohorts(array $members)
    {
        Log::info('Removing multiple users from cohorts', ['members' => $members]);

        // Validar que cada miembro tenga userid y cohortid
        foreach ($members as $member) {
            if (!isset($member['userid']) || !isset($member['cohortid'])) {
                return [
                    'status' => 'error',
                    'message' => 'Cada miembro debe tener userid y cohortid'
                ];
            }
        }

        $data = [
            'members' => $members
        ];

        return $this->sendRequest('core_cohort_delete_cohort_members', $data);
    }

    /**
     * Obtener los cohorts de un usuario.
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
     * Eliminar un usuario de todos sus cohorts.
     */
    public function removeUserFromAllCohorts($username)
    {
        Log::info('Removing user from all cohorts', ['username' => $username]);

        // Necesitamos el servicio de usuarios para obtener el usuario
        $userService = new MoodleUserService();
        $userResult = $userService->getUserByUsername($username);

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

        // Preparamos los datos para eliminar de todos los cohorts de una vez
        $membersToRemove = [];
        foreach ($cohortsResult['data']['cohorts'] as $cohort) {
            $membersToRemove[] = [
                'userid' => $userId,
                'cohortid' => $cohort['id']
            ];
        }

        // Eliminamos al usuario de todos los cohorts en una sola llamada
        $result = $this->removeUsersFromCohorts($membersToRemove);

        if ($result['status'] !== 'success') {
            Log::error('Error removing user from all cohorts', [
                'username' => $username,
                'user_id' => $userId,
                'error' => $result['message']
            ]);
        }

        return [
            'status' => $result['status'],
            'message' => $result['status'] === 'success' 
                ? 'Usuario eliminado de todos los cohorts' 
                : $result['message'],
            'cohorts_removed' => count($membersToRemove)
        ];
    }

    /**
     * Formatea los datos del cohort para asegurar que cumplan con la estructura requerida.
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
}
