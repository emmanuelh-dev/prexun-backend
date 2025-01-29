<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

/**
 * @group Student Management
 * 
 * APIs for managing students
 * 
 * Example request for creating a student (POST /api/students):
 * {
 *    "username": "john.doe",
 *    "firstname": "John",
 *    "lastname": "Doe", 
 *    "email": "john.doe@example.com",
 *    "phone": "1234567890",
 *    "type": "preparatoria",
 *    "campus_id": 1
 * }
 *
 * Example request for updating a student (PUT /api/students/{id}):
 * {
 *    "name": "John Smith",
 *    "email": "john.smith@example.com",
 *    "phone": "0987654321",
 *    "cohort_id": 1,
 *    "status": "active"
 * }
 */
class StudentController extends Controller
{
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

            $student = Student::create($request->all());
            $promo = Promocion::findOrFail($request->promo_id);

            if (count($promo->pagos) > 0) {
                foreach ($promo->pagos as $pago) {
                    Transaction::create([
                        'campus_id' => $request->campus_id,
                        'student_id' => $student->id,
                        'promocion_id' => $promo->id,
                        'amount' => $pago['amount'],
                        'expiration_date' => $pago['date'],
                        'notes' => isset($pago['description']) ? $pago['description'] : "",
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

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,txt|max:10240',
            'campus_id' => 'required|exists:campuses,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$request->hasFile('file')) {
            return response()->json([
                'message' => 'No se encontró el archivo',
                'debug' => [
                    'files' => $request->allFiles(),
                    'content' => $request->all()
                ]
            ], 422);
        }

        $file = $request->file('file');
        $campus_id = $request->campus_id;

        try {
            $students = [];
            $skippedRecords = [];
            $handle = fopen($file->getPathname(), 'r');

            if (!$handle) {
                throw new \Exception('No se pudo abrir el archivo');
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                throw new \Exception('No se pudieron leer los encabezados del archivo');
            }

            $expectedHeaders = ['username', 'firstname', 'lastname', 'email', 'type'];

            if ($headers !== $expectedHeaders) {
                fclose($handle);
                return response()->json([
                    'message' => 'Formato de archivo inválido',
                    'error' => 'Los encabezados del CSV no coinciden con el formato esperado',
                    'expected' => $expectedHeaders,
                    'received' => $headers
                ], 422);
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 4) {
                    $skippedRecords[] = [
                        'row' => $row,
                        'reason' => 'Fila incompleta'
                    ];
                    continue;
                }

                // Verificar si el username ya existe
                if (Student::withTrashed()->where('username', $row[0])->exists()) {
                    $skippedRecords[] = [
                        'row' => $row,
                        'reason' => 'Username ya existe: ' . $row[0]
                    ];
                    continue;
                }

                // Verificar si el email existe (si está presente en el CSV)
                if (isset($row[3]) && !empty($row[3]) && Student::withTrashed()->where('email', $row[3])->exists()) {
                    $skippedRecords[] = [
                        'row' => $row,
                        'reason' => 'Email ya existe: ' . $row[3]
                    ];
                    continue;
                }



                $students[] = [
                    'username' => $row[0],
                    'firstname' => $row[1],
                    'lastname' => $row[2],
                    'type' => $row[4],
                    'email' => isset($row[3]) ? $row[3] : null,
                    'phone' => isset($row[5]) ? $row[5] : null,
                    'campus_id' => $campus_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            fclose($handle);

            // Bulk insert valid students
            if (!empty($students)) {
                Student::insert($students);
            }

            return response()->json([
                'message' => 'Estudiantes importados exitosamente',
                'imported_count' => count($students),
                'skipped_count' => count($skippedRecords),
                'skipped_records' => $skippedRecords
            ]);
        } catch (\Exception $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }

            return response()->json([
                'message' => 'Error al importar estudiantes',
                'error' => $e->getMessage(),
                'debug' => [
                    'file_info' => $file ? [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType()
                    ] : null
                ]
            ], 500);
        }
    }

    /**
     * Get active students.
     */
    public function getActive()
    {
        $students = Student::where('status', 'active')->with('cohort')->get();
        return response()->json($students);
    }
}
