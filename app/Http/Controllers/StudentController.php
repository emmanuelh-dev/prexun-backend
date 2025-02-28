<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use App\Models\Student;
use App\Models\Transaction;
use App\Services\Moodle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class StudentController extends Controller
{
    protected $moodleService;

    public function __construct(Moodle $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * Display a listing of students.
     */
    public function index($campus_id = null)
    {
        $query = Student::with(['period', 'transactions', 'municipio', 'prepa', 'facultad', 'carrera']);

        if ($campus_id) {
            $query->where('campus_id', $campus_id);
        }

        $students = $query->get()->map(function ($student) {
            $periodCost = $student->period?->price ?? 0;
            $totalPaid = $student->transactions->sum('amount');

            $student = $student->toArray();
            $student['current_debt'] = $periodCost - $totalPaid;

            return $student;
        });

        return response()->json($students);
    }

    /**
     * Store a new student.
     */
    public function store(Request $request)
    {
        $validator = $this->validateStudent($request);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create student in database
            $student = Student::create($request->all());
            $username = (string) $student->id;

            // Create user in Moodle
            $moodleUser = $this->prepareMoodleUser($student);
            $moodleResponse = $this->moodleService->createUser([$moodleUser]);
            Log::info('Moodle User Created', ['moodle_user' => $moodleResponse]);

            // Wait a bit for Moodle to register the user
            sleep(2);

            // Get cohort ID using the student's group name
            $cohortName = $student->period->name . $student->grupo->name;
            $cohortId = $this->moodleService->getCohortIdByName($cohortName);

            if (!$cohortId) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cohort not found in Moodle'
                ], 500);
            }

            // Add user to cohort
            $cohortResponse = $this->moodleService->addUserToCohort($username, $cohortId);

            if ($cohortResponse['status'] !== 'success') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error adding user to cohort in Moodle',
                    'error' => $cohortResponse['message']
                ], 500);
            }

            // The commented code for creating transactions has been removed as it appears to be unused

            DB::commit();
            $student->load('charges');

            return response()->json($student, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating student and charges',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hard update of student data including Moodle sync.
     */
    public function hardUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:students,id',
            'email' => 'required|email|unique:students,email,' . $request->id,
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::findOrFail($request->id);
        $student->update($request->only(['email', 'firstname', 'lastname']));

        $this->syncMoodleUserEmail($student);

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student,
        ]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'firstname' => 'string|max:255',
            'lastname' => 'string|max:255',
            'email' => 'email',
            'phone' => 'string|max:20',
            'type' => 'in:preparatoria,facultad',
            'campus_id' => 'exists:campuses,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student->update($request->all());

        return response()->json($student);
    }

    /**
     * Display the specified student.
     */
    public function show(Student $student)
    {
        return response()->json($student->load('transactions'));
    }

    /**
     * Remove the specified student.
     */
    /**
     * Remove the specified student and optionally delete from Moodle.
     */
    public function destroy(Student $student, Request $request)
    {
        try {
            // Attempt to delete user from Moodle
            $username = (string) $student->id;
            $moodleUser = $this->moodleService->getUserByUsername($username);

            if ($moodleUser['status'] === 'success' && isset($moodleUser['data']['id'])) {
                $userId = $moodleUser['data']['id'];
                $this->moodleService->deleteUser($userId);
                Log::info('Moodle user deleted', ['student_id' => $student->id, 'moodle_id' => $userId]);
            } else {
                Log::warning('Moodle user not found for deletion', ['student_id' => $student->id]);
            }

            // Delete from database
            if ($request->boolean('permanent') === true) {
                $student->forceDelete();
                return response()->json(['message' => 'Estudiante eliminado permanentemente y sincronizado con Moodle']);
            }

            $student->delete();
            return response()->json(['message' => 'Estudiante eliminado y sincronizado con Moodle']);
        } catch (\Exception $e) {
            Log::error('Error deleting student from Moodle', [
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'El estudiante fue eliminado de la base de datos, pero ocurrió un error al sincronizar con Moodle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted student.
     */
    public function restore($id)
    {
        $student = Student::withTrashed()->findOrFail($id);
        $student->restore();
        return response()->json(['message' => 'Estudiante restaurado']);
    }

    /**
     * Get students by cohort.
     */
    public function getByCohort($cohortId)
    {
        $students = Student::where('cohort_id', $cohortId)->with('cohort')->get();
        return response()->json($students);
    }

    /**
     * Sync all students with Moodle.
     */
    public function syncMoodle(Request $request)
    {
        $students = Student::get();

        if ($students->isEmpty()) {
            return response()->json(['message' => 'No students found.'], 404);
        }

        try {
            $responses = [];
            $users = $this->prepareMoodleUsersForSync($students);

            // Process in batches of 100 users
            $userChunks = array_chunk($users, 100, true);

            foreach ($userChunks as $chunk) {
                $responses[] = $this->moodleService->createUser($chunk);
            }

            return response()->json($responses, 200);
        } catch (\Exception $e) {
            Log::error("Moodle Sync Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to sync with Moodle'], 500);
        }
    }

    /**
     * Export students to CSV.
     */
    public function exportCsv()
    {
        $fileName = 'students.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['username', 'email', 'grupo'];
        $students = Student::with('grupo')->get(['id', 'email', 'grupo_id']);

        $callback = function () use ($students, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->email,
                    $student->grupo ? $student->grupo->name : 'Sin grupo'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Get active students.
     */
    public function getActive()
    {
        $students = Student::where('status', 'active')->with('cohort')->get();
        return response()->json($students);
    }

    /**
     * Validate student data.
     */
    private function validateStudent(Request $request)
    {
        return Validator::make($request->all(), [
            'firstname' => 'string|max:255',
            'lastname' => 'string|max:255',
            'email' => 'required|email|unique:students',
            'phone' => 'string|max:20',
            'type' => 'in:preparatoria,facultad',
            'campus_id' => 'required|exists:campuses,id',
        ]);
    }

    /**
     * Prepare Moodle user data for a single student.
     */
    private function prepareMoodleUser(Student $student)
    {
        return [
            "username" => (string) $student->id,
            "firstname" => strtoupper($student->firstname),
            "lastname" => strtoupper($student->lastname),
            "email" => $student->email,
            "createpassword" => true,
            "auth" => "manual",
            "idnumber" => (string) $student->id,
            "lang" => "es_mx",
            "calendartype" => "gregorian",
            "timezone" => "America/Mexico_City"
        ];
    }

    /**
     * Prepare Moodle users data for bulk sync.
     */
    private function prepareMoodleUsersForSync($students)
    {
        $users = [];

        foreach ($students as $student) {
            $users["user_{$student->id}"] = $this->prepareMoodleUser($student);
        }

        return $users;
    }

    /**
     * Sync student email with Moodle.
     */
    private function syncMoodleUserEmail(Student $student)
    {
        $result = $this->moodleService->getUserByUsername($student->id);

        if ($result['status'] === 'success') {
            $user = $result['data'];
            $this->moodleService->updateUser([[
                "id" => $user['id'],
                "email" => $student->email,
                "firstname" => $student->firstname,
                "lastname" => $student->lastname
            ]]);
        }

        return $result;
    }
}
