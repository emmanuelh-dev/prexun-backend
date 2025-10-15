<?php

namespace App\Services\Moodle;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoodleGradeService
{
    protected string $moodleUrl;
    protected string $token;

    public function __construct()
    {
        $this->moodleUrl = config('moodle.url');
        $this->token = config('moodle.token');
    }

    public function getUserGradeItems(int $courseId, int $userId = 0, int $groupId = 0)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'gradereport_user_get_grade_items',
                'moodlewsrestformat' => 'json',
                'courseid' => $courseId,
                'userid' => $userId,
                'groupid' => $groupId,
            ];

            $response = Http::get($this->moodleUrl . '/webservice/rest/server.php', $params);

            if ($response->failed()) {
                Log::error('Error al obtener calificaciones del usuario de Moodle', [
                    'course_id' => $courseId,
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                Log::error('Moodle API error al obtener calificaciones', [
                    'course_id' => $courseId,
                    'user_id' => $userId,
                    'error' => $data
                ]);
                return null;
            }

            // Log para debugging - muestra la estructura de las actividades del curso
            Log::info('Respuesta de Moodle - gradereport_user_get_grade_items', [
                'course_id' => $courseId,
                'user_id' => $userId,
                'response_structure' => json_encode($data, JSON_PRETTY_PRINT),
                'items_count' => isset($data['usergrades'][0]['gradeitems']) ? count($data['usergrades'][0]['gradeitems']) : 0
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception al obtener calificaciones de Moodle', [
                'course_id' => $courseId,
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getCourseGradesOverview(int $userId)
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'gradereport_overview_get_course_grades',
                'moodlewsrestformat' => 'json',
                'userid' => $userId,
            ];

            $response = Http::get($this->moodleUrl . '/webservice/rest/server.php', $params);

            if ($response->failed()) {
                Log::error('Error al obtener resumen de calificaciones de Moodle', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                Log::error('Moodle API error al obtener resumen de calificaciones', [
                    'user_id' => $userId,
                    'error' => $data
                ]);
                return null;
            }

            // Log para debugging - muestra la estructura completa de la respuesta
            Log::info('Respuesta de Moodle - gradereport_overview_get_course_grades', [
                'user_id' => $userId,
                'response_structure' => json_encode($data, JSON_PRETTY_PRINT),
                'grades_count' => isset($data['grades']) ? count($data['grades']) : 0
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception al obtener resumen de calificaciones de Moodle', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function getUserGradesForAllCourses(int $userId, array $courseIds = [])
    {
        $allGrades = [];

        foreach ($courseIds as $courseId) {
            $grades = $this->getUserGradeItems($courseId, $userId);
            
            if ($grades && isset($grades['usergrades']) && !empty($grades['usergrades'])) {
                $allGrades[] = [
                    'course_id' => $courseId,
                    'grades' => $grades['usergrades'][0] ?? null,
                ];
            }
        }

        return $allGrades;
    }

    public function saveAssignmentGrade(int $assignmentId, int $userId, float $grade, string $feedback = '')
    {
        try {
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'mod_assign_save_grade',
                'moodlewsrestformat' => 'json',
                'assignmentid' => $assignmentId,
                'userid' => $userId,
                'grade' => $grade,
                'attemptnumber' => -1,
                'addattempt' => 1,
                'workflowstate' => '',
                'applytoall' => 1,
                'plugindata[assignfeedbackcomments_editor][text]' => $feedback,
                'plugindata[assignfeedbackcomments_editor][format]' => 1,
            ];

            $response = Http::asForm()->post($this->moodleUrl . '/webservice/rest/server.php', $params);

            if ($response->failed()) {
                Log::error('Error al guardar calificación en Moodle', [
                    'assignment_id' => $assignmentId,
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (isset($data['exception'])) {
                Log::error('Moodle API error al guardar calificación', [
                    'assignment_id' => $assignmentId,
                    'user_id' => $userId,
                    'error' => $data
                ]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception al guardar calificación en Moodle', [
                'assignment_id' => $assignmentId,
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
