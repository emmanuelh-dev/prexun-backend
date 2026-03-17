<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAssignment;
use App\Services\Moodle\MoodleService;
use Illuminate\Support\Facades\Log;

class StudentGradesService
{
    protected MoodleService $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    public function getStudentGradesByMatricula(string $matricula): array
    {
        try {
            $student = Student::find($matricula);

            if (!$student) {
                return [
                    'success' => false,
                    'error' => "No se encontró estudiante con matrícula: {$matricula}"
                ];
            }

            return $this->getStudentGrades($student);
        } catch (\Exception $e) {
            Log::error('Error obteniendo calificaciones por matrícula', [
                'matricula' => $matricula,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getStudentGradesByPhone(string $phoneNumber): array
    {
        try {
            $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

            $student = Student::where('phone', $normalizedPhone)
                ->orWhere('tutor_phone', $normalizedPhone)
                ->first();

            if (!$student) {
                return [
                    'success' => false,
                    'error' => "No se encontró estudiante con número: {$phoneNumber}"
                ];
            }

            return $this->getStudentGrades($student);
        } catch (\Exception $e) {
            Log::error('Error obteniendo calificaciones por teléfono', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getStudentGrades(Student $student): array
    {
        try {
            // Este método ahora lanza una excepción controlada si no hay Moodle ID
            $this->ensureStudentHasMoodleId($student);

            $gradesOverview = $this->moodleService->grades()->getCourseGradesOverview($student->moodle_id);
            $coursesOverview = $this->moodleService->courses()->getUserEnrolledCourses($student->moodle_id);

            if (!$gradesOverview || !isset($gradesOverview['grades'])) {
                return [
                    'success' => true,
                    'data' => [
                        'student' => $this->formatStudentData($student),
                        'courses_count' => 0,
                        'grades' => []
                    ]
                ];
            }

            $assignments = StudentAssignment::where('student_id', $student->id)
                ->where('is_active', true)
                ->with(['grupo', 'semanaIntensiva', 'carrera'])
                ->get();

            $courseNameMapping = $this->buildCourseNameMapping($assignments);
            $coursesDetailsMapping = $this->buildCoursesDetailsMapping($coursesOverview);

            $gradesWithCourseInfo = $this->processGrades(
                $gradesOverview['grades'],
                $courseNameMapping,
                $coursesDetailsMapping,
                $student
            );

            return [
                'success' => true,
                'data' => [
                    'student' => $this->formatStudentData($student),
                    'courses_count' => count($gradesWithCourseInfo),
                    'grades' => $gradesWithCourseInfo,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error en getStudentGrades', [
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function formatStudentData(Student $student): array
    {
        return [
            'id' => $student->id,
            'matricula' => $student->id,
            'firstname' => $student->firstname,
            'lastname' => $student->lastname,
            'moodle_id' => $student->moodle_id,
        ];
    }

    private function buildCourseNameMapping($assignments): array
    {
        $courseNameMapping = [];
        foreach ($assignments as $assignment) {
            if ($assignment->grupo && $assignment->grupo->moodle_id) {
                $courseNameMapping[$assignment->grupo->moodle_id] = [
                    'name' => $assignment->grupo->name,
                    'type' => 'Grupo',
                    'carrera' => $assignment->carrera->name ?? null,
                ];
            }
            if ($assignment->semanaIntensiva && $assignment->semanaIntensiva->moodle_id) {
                $courseNameMapping[$assignment->semanaIntensiva->moodle_id] = [
                    'name' => $assignment->semanaIntensiva->name,
                    'type' => 'Semana Intensiva',
                    'carrera' => $assignment->carrera->name ?? null,
                ];
            }
        }
        return $courseNameMapping;
    }

    private function buildCoursesDetailsMapping($coursesOverview): array
    {
        $coursesDetailsMapping = [];
        if ($coursesOverview && is_array($coursesOverview)) {
            foreach ($coursesOverview as $course) {
                $coursesDetailsMapping[$course['id']] = [
                    'shortname' => $course['shortname'] ?? '',
                    'displayname' => $course['displayname'] ?? '',
                    'course_image' => $course['courseimage'] ?? null,
                    // ... (demás campos)
                ];
            }
        }
        return $coursesDetailsMapping;
    }

    private function processGrades(array $grades, array $courseNameMapping, array $coursesDetailsMapping, Student $student): array
    {
        $gradesWithCourseInfo = [];
        foreach ($grades as $courseGrade) {
            $courseId = $courseGrade['courseid'];
            $courseData = $this->buildCourseData($courseGrade, $courseNameMapping[$courseId] ?? null, $coursesDetailsMapping[$courseId] ?? null);

            if ($courseGrade['rawgrade'] !== null) {
                $this->addActivitiesData($courseData, $courseId, $student);
            }
            $gradesWithCourseInfo[] = $courseData;
        }
        return $gradesWithCourseInfo;
    }

    private function buildCourseData(array $courseGrade, ?array $courseInfo, ?array $courseDetails): array
    {
        return [
            'course_id' => $courseGrade['courseid'],
            'course_name' => $courseInfo['name'] ?? $courseDetails['displayname'] ?? $courseGrade['coursename'] ?? 'Curso desconocido',
            'course_type' => $courseInfo['type'] ?? 'Curso',
            'grade' => $courseGrade['grade'] ?? null,
            'rawgrade' => $courseGrade['rawgrade'] ?? null,
            'activities' => [],
            'activities_count' => 0,
        ];
    }

    private function addActivitiesData(array &$courseData, int $courseId, Student $student): void
    {
        $gradeItems = $this->moodleService->grades()->getUserGradeItems($courseId, $student->moodle_id);
        if (!$gradeItems || !isset($gradeItems['usergrades'][0]['gradeitems']))
            return;

        $activities = [];
        foreach ($gradeItems['usergrades'][0]['gradeitems'] as $item) {
            if (isset($item['itemtype']) && $item['itemtype'] !== 'course') {
                $activities[] = [
                    'name' => $item['itemname'] ?? 'Actividad',
                    'grade' => $item['gradeformatted'] ?? '-',
                ];
            }
        }
        $courseData['activities'] = $activities;
        $courseData['activities_count'] = count($activities);
    }

    private function ensureStudentHasMoodleId(Student $student): void
    {
        if (!$student->moodle_id) {
            $username = (string) $student->id;
            try {
                $moodleUser = $this->moodleService->users()->getUserByUsername($username);

                if ($moodleUser && isset($moodleUser['status']) && $moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                    $student->moodle_id = $moodleUser['data']['id'];
                    $student->save();
                } else {
                    // No lanzamos un 500, lanzamos un mensaje que el front pueda mostrar
                    throw new \Exception("El alumno con matrícula {$username} no existe en Moodle.");
                }
            } catch (\Exception $e) {
                throw new \Exception("Error al conectar con Moodle: " . $e->getMessage());
            }
        }
    }

    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^+\d]/', '', $phoneNumber);
        return str_starts_with($cleaned, '+') ? $cleaned : '+' . $cleaned;
    }
}