<?php

namespace App\Services\Moodle;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoodleCourseService
{
    protected string $moodleUrl;
    protected string $token;

    public function __construct()
    {
        $this->moodleUrl = config('moodle.url');
        $this->token = config('moodle.token');
    }

    public function getCourses(array $courseIds = [])
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_get_courses',
                'moodlewsrestformat' => 'json',
            ];

            if (!empty($courseIds)) {
                foreach ($courseIds as $index => $courseId) {
                    $params["options[ids][{$index}]"] = $courseId;
                }
            }

            $response = Http::get($this->moodleUrl . '/webservice/rest/server.php', $params);

            if ($response->failed()) {
                Log::error('Error al obtener cursos de Moodle', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                Log::error('Moodle API error al obtener cursos', ['error' => $data]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception al obtener cursos de Moodle', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getCourseContents(int $courseId)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_course_get_contents',
                'moodlewsrestformat' => 'json',
                'courseid' => $courseId,
            ];

            $response = Http::get($this->moodleUrl . '/webservice/rest/server.php', $params);

            if ($response->failed()) {
                Log::error('Error al obtener contenidos del curso de Moodle', [
                    'course_id' => $courseId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                Log::error('Moodle API error al obtener contenidos del curso', [
                    'course_id' => $courseId,
                    'error' => $data
                ]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception al obtener contenidos del curso de Moodle', [
                'course_id' => $courseId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getUserEnrolledCourses(int $userId)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'core_enrol_get_users_courses',
                'moodlewsrestformat' => 'json',
                'userid' => $userId,
            ];

            $response = Http::get($this->moodleUrl . '/webservice/rest/server.php', $params);

            if ($response->failed()) {
                Log::error('Error al obtener cursos del usuario de Moodle', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                Log::error('Moodle API error al obtener cursos del usuario', [
                    'user_id' => $userId,
                    'error' => $data
                ]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception al obtener cursos del usuario de Moodle', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
