<?php

namespace App\Services\Moodle;

use Illuminate\Support\Facades\Log;
    
class MoodleUserService extends BaseMoodleService
{
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
     * Obtener usuario por username.
     */
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
     * Actualizar usuarios en Moodle.
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

    /**
     * Suspender (inactivar) o activar usuarios en Moodle.
     * 
     * @param array $users Array de usuarios con estructura: 
     *                     [['id' => moodle_user_id, 'suspended' => 1|0], ...]
     *                     suspended: 1 = suspender usuario, 0 = activar usuario
     * @return array Respuesta de la API de Moodle
     */
    public function suspendUser(array $users)
    {
        Log::info('Suspending/Activating users in Moodle', ['users' => $users]);

        // Validar que los usuarios tengan la estructura correcta
        foreach ($users as $user) {
            if (!isset($user['id']) || !isset($user['suspended'])) {
                return [
                    'status' => 'error',
                    'message' => 'Each user must have "id" and "suspended" fields'
                ];
            }

            if (!is_numeric($user['id']) || !in_array($user['suspended'], [0, 1])) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid user data: id must be numeric and suspended must be 0 or 1'
                ];
            }
        }

        $response = $this->sendRequest('core_user_update_users', $this->formatUsers($users));

        if ($response['status'] === 'success') {
            return [
                'status' => 'success',
                'data' => $response['data'] ?? [],
                'message' => 'Users suspended/activated successfully'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Error suspending/activating users in Moodle',
            'response' => $response
        ];
    }

    /**
     * Eliminar usuarios de Moodle.
     */
    public function deleteUser($userId)
    {
        Log::info('Deleting user from Moodle', ['moodle_user_id' => $userId]);

        if (is_array($userId)) {
            $data = [];
            foreach ($userId as $index => $id) {
                $data["userids[$index]"] = $id;
            }
        } else {
            $data = [
                'userids[0]' => $userId
            ];
        }

        return $this->sendRequest('core_user_delete_users', $data);
    }
}
