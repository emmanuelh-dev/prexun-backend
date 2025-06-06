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
