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
            $periodCost = $student->period ? $student->period->price : 0;

            $totalPaid = $student->transactions->sum('amount');

            $currentDebt = $periodCost - $totalPaid;

            $student = $student->toArray();
            $student['current_debt'] = $currentDebt;

            return $student;
        });

        return response()->json($students);
    }
    /**
     * Store a new student.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'string|max:255',
            'lastname' => 'string|max:255',
            'email' => 'required|email|unique:students',
            'phone' => 'string|max:20',
            'type' => 'in:preparatoria,facultad',
            'campus_id' => 'required|exists:campuses,id',
            'promo_id' => 'required|exists:promociones,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // 🔹 Crear el estudiante en la base de datos
            $student = Student::create($request->all());
            $username = (string) $student->id;

            // 🔹 Crear usuario en Moodle
            $moodleUser = [
                "username" => $username,
                "firstname" => strtoupper($student->firstname),
                "lastname" => strtoupper($student->lastname),
                "email" => $student->email,
                "createpassword" => true,
                "auth" => "manual",
                "idnumber" => $username,
                "lang" => "es_mx",
                "calendartype" => "gregorian",
                "timezone" => "America/Mexico_City"
            ];

            $moodeUser =  $this->moodleService->createUser([$moodleUser]);
            Log::info('Moodle User Created', ['moodle_user' => $moodeUser]);
            // 🔹 Esperar un poco para que Moodle registre el usuario
            sleep(2);

            // 🔹 Obtener el ID del cohort usando el nombre del grupo del estudiante
            $promo = Promocion::findOrFail($request->promo_id);
            $cohortName = $student->period->name . $student->grupo->name;
            $cohortId = $this->moodleService->getCohortIdByName($cohortName);

            if (!$cohortId) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cohort not found in Moodle'
                ], 500);
            }

            // 🔹 Agregar usuario al cohort
            $cohortResponse = $this->moodleService->addUserToCohort($username, $cohortId);

            if ($cohortResponse['status'] !== 'success') {
                DB::rollBack();
                return response()->json([
                    'message' => 'Error adding user to cohort in Moodle',
                    'error' => $cohortResponse['message']
                ], 500);
            }

            // 🔹 Crear transacciones de pago
            if (count($promo->pagos) > 0) {
                foreach ($promo->pagos as $pago) {
                    Transaction::create([
                        'campus_id' => $request->campus_id,
                        'student_id' => $student->id,
                        'promocion_id' => $promo->id,
                        'amount' => $pago['amount'],
                        'expiration_date' => $pago['date'],
                        'notes' => $pago['description'] ?? "",
                        'status' => 'pending',
                        'type' => 'payment_plan',
                        'uuid' => Str::uuid()
                    ]);
                }
            } else {
                Transaction::create([
                    'campus_id' => $request->campus_id,
                    'student_id' => $student->id,
                    'promocion_id' => $promo->id,
                    'amount' => $promo->cost,
                    'expiration_date' => null,
                    'status' => 'pending',
                    'type' => 'single_payment',
                    'uuid' => Str::uuid()
                ]);
            }

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

    public function hardUpdate(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:students,id',
            'email' => 'required|email|unique:students,email,' . $request->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::findOrFail($request->id);

        $student->update($request->only([
            'email',
        ]));
        $result = $this->moodleService->getUserByUsername($student->id);
        if ($result['status'] === 'success') {
            $user = $result['data'];
            $this->moodleService->updateUser([[
                "id" => $user['id'],
                "email" => $student->email,
            ]]);
        }

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
            'email' => ['email'],
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
    public function destroy(Student $student, Request $request)
    {

        if ($request->boolean('permanent') === true) {
            $student->forceDelete();

            return response()->json(['message' => 'Estudiante eliminado permanentemente']);
        }

        $student->delete();
        return response()->json(['message' => 'Estudiante eliminado']);
    }

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

    public function syncMoodle(Request $request)
    {
        // Obtener estudiantes del periodo
        $students = Student::get();

        if ($students->isEmpty()) {
            return response()->json(['message' => 'No students found for this period.'], 404);
        }

        // Convertir datos al formato que requiere Moodle
        $users = [];

        foreach ($students as $student) {
            $users["user_{$student->id}"] = [
                "username" => $student->id,
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

        // Procesar en lotes de 100 usuarios
        $userChunks = array_chunk($users, 100, true);

        try {
            $responses = [];
            foreach ($userChunks as $chunk) {
                $response = $this->moodleService->createUser($chunk);

                $responses[] = $response;
            }
            return response()->json($responses, 200);
        } catch (\Exception $e) {
            Log::error("Moodle Sync Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to sync with Moodle'], 500);
        }
    }

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

        $columns = ['Username', 'Grupo'];

        $students = Student::with('grupo')->get(['id', 'grupo_id']);

        $callback = function () use ($students, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($students as $student) {
                fputcsv($file, [
                    $student->id,
                    $student->grupo ? $student->grupo->name : 'Sin grupo'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function getActive()
    {
        $students = Student::where('status', 'active')->with('cohort')->get();
        return response()->json($students);
    }
}
