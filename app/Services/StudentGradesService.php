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
                'error' => 'Error obteniendo calificaciones: ' . $e->getMessage()
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
                'error' => 'Error obteniendo calificaciones: ' . $e->getMessage()
            ];
        }
    }

    public function getStudentGrades(Student $student): array
    {
        try {
            $this->ensureStudentHasMoodleId($student);

            $gradesOverview = $this->moodleService->grades()->getCourseGradesOverview($student->moodle_id);
            $coursesOverview = $this->moodleService->courses()->getUserEnrolledCourses($student->moodle_id);

            if (!$gradesOverview || !isset($gradesOverview['grades'])) {
                return [
                    'success' => true,
                    'data' => [
                        'student' => [
                            'id' => $student->id,
                            'matricula' => $student->id,
                            'firstname' => $student->firstname,
                            'lastname' => $student->lastname,
                            'moodle_id' => $student->moodle_id,
                        ],
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
                    'student' => [
                        'id' => $student->id,
                        'matricula' => $student->id,
                        'firstname' => $student->firstname,
                        'lastname' => $student->lastname,
                        'moodle_id' => $student->moodle_id,
                    ],
                    'courses_count' => count($gradesWithCourseInfo),
                    'grades' => $gradesWithCourseInfo,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error obteniendo calificaciones del estudiante', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error al obtener calificaciones: ' . $e->getMessage()
            ];
        }
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
                    'fullname' => $course['fullname'] ?? '',
                    'displayname' => $course['displayname'] ?? '',
                    'course_image' => $course['courseimage'] ?? null,
                    'visible' => $course['visible'] ?? true,
                    'summary' => $course['summary'] ?? '',
                    'format' => $course['format'] ?? 'topics',
                    'enrolled_user_count' => $course['enrolledusercount'] ?? 0,
                    'show_grades' => $course['showgrades'] ?? true,
                    'enable_completion' => $course['enablecompletion'] ?? false,
                    'completion_user_tracked' => $course['completionusertracked'] ?? false,
                    'progress' => $course['progress'] ?? null,
                    'completed' => $course['completed'] ?? false,
                    'start_date' => $course['startdate'] ?? null,
                    'end_date' => $course['enddate'] ?? null,
                    'last_access' => $course['lastaccess'] ?? null,
                    'is_favourite' => $course['isfavourite'] ?? false,
                    'hidden' => $course['hidden'] ?? false,
                    'category' => $course['category'] ?? null,
                ];
            }
        }

        return $coursesDetailsMapping;
    }

    private function processGrades(
        array $grades,
        array $courseNameMapping,
        array $coursesDetailsMapping,
        Student $student
    ): array {
        $gradesWithCourseInfo = [];

        foreach ($grades as $courseGrade) {
            $courseId = $courseGrade['courseid'];
            $courseInfo = $courseNameMapping[$courseId] ?? null;
            $courseDetails = $coursesDetailsMapping[$courseId] ?? null;

            $courseData = $this->buildCourseData($courseGrade, $courseInfo, $courseDetails);
            
            if ($courseGrade['rawgrade'] !== null) {
                $this->addActivitiesData($courseData, $courseId, $student);
            }

            $gradesWithCourseInfo[] = $courseData;
        }

        return $gradesWithCourseInfo;
    }

    private function buildCourseData(
        array $courseGrade,
        ?array $courseInfo,
        ?array $courseDetails
    ): array {
        return [
            'course_id' => $courseGrade['courseid'],
            'course_name' => $courseInfo['name'] ?? $courseDetails['displayname'] ?? $courseGrade['coursename'] ?? 'Curso desconocido',
            'course_type' => $courseInfo['type'] ?? 'Curso',
            'carrera_name' => $courseInfo['carrera'] ?? null,
            'course_shortname' => $courseDetails['shortname'] ?? $courseGrade['courseshortname'] ?? '',
            'course_fullname' => $courseDetails['fullname'] ?? null,
            'course_image' => $courseDetails['course_image'] ?? null,
            'course_visible' => $courseDetails['visible'] ?? true,
            'course_summary' => $courseDetails['summary'] ?? null,
            'course_format' => $courseDetails['format'] ?? 'topics',
            'enrolled_users' => $courseDetails['enrolled_user_count'] ?? 0,
            'show_grades' => $courseDetails['show_grades'] ?? true,
            'completion_enabled' => $courseDetails['enable_completion'] ?? false,
            'completion_tracked' => $courseDetails['completion_user_tracked'] ?? false,
            'progress' => $courseDetails['progress'] ?? null,
            'completed' => $courseDetails['completed'] ?? false,
            'start_date' => $courseDetails['start_date'] ?? null,
            'end_date' => $courseDetails['end_date'] ?? null,
            'last_access' => $courseDetails['last_access'] ?? null,
            'is_favourite' => $courseDetails['is_favourite'] ?? false,
            'hidden' => $courseDetails['hidden'] ?? false,
            'category' => $courseDetails['category'] ?? null,
            'grade' => $courseGrade['grade'] ?? null,
            'rawgrade' => $courseGrade['rawgrade'] ?? null,
            'rank' => $courseGrade['rank'] ?? null,
            'activities' => [],
            'activities_count' => 0,
            'course_grade_details' => null,
        ];
    }

    private function addActivitiesData(array &$courseData, int $courseId, Student $student): void
    {
        $gradeItems = $this->moodleService->grades()->getUserGradeItems($courseId, $student->moodle_id);
        
        if (!$gradeItems || !isset($gradeItems['usergrades']) || empty($gradeItems['usergrades'])) {
            return;
        }

        $userGrade = $gradeItems['usergrades'][0];
        
        if (!isset($userGrade['gradeitems'])) {
            return;
        }

        $activities = [];
        $courseTotalItem = null;
        
        foreach ($userGrade['gradeitems'] as $item) {
            if (isset($item['itemtype']) && $item['itemtype'] === 'course') {
                $courseTotalItem = $item;
            } else {
                $activities[] = [
                    'id' => $item['id'] ?? null,
                    'name' => $item['itemname'] ?? 'Actividad sin nombre',
                    'type' => $item['itemtype'] ?? 'unknown',
                    'module' => $item['itemmodule'] ?? null,
                    'grade' => $item['gradeformatted'] ?? '-',
                    'rawgrade' => $item['graderaw'] ?? null,
                    'max_grade' => $item['grademax'] ?? null,
                    'min_grade' => $item['grademin'] ?? null,
                    'percentage' => $item['percentageformatted'] ?? null,
                    'feedback' => $item['feedback'] ?? null,
                    'weight' => $item['weightformatted'] ?? null,
                ];
            }
        }
        
        $courseData['activities'] = $activities;
        $courseData['activities_count'] = count($activities);
        
        if ($courseTotalItem) {
            $courseData['course_grade_details'] = [
                'max_grade' => $courseTotalItem['grademax'] ?? null,
                'min_grade' => $courseTotalItem['grademin'] ?? null,
                'percentage' => $courseTotalItem['percentageformatted'] ?? null,
            ];
        }
    }

    private function ensureStudentHasMoodleId(Student $student): void
    {
        if (!$student->moodle_id) {
            $username = (string) $student->id;
            $moodleUser = $this->moodleService->users()->getUserByUsername($username);
            
            if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                $student->moodle_id = $moodleUser['data']['id'];
                $student->save();
                
                Log::info('Moodle ID fetched and saved for student', [
                    'student_id' => $student->id,
                    'moodle_id' => $student->moodle_id
                ]);
            } else {
                Log::warning('Failed to fetch Moodle ID for student', [
                    'student_id' => $student->id,
                    'username' => $username,
                    'error' => $moodleUser['message'] ?? 'Unknown error'
                ]);
                
                throw new \Exception('Failed to fetch Moodle ID for student: ' . $student->id);
            }
        }
    }

    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^+\d]/', '', $phoneNumber);
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }
        return $cleaned;
    }
}
