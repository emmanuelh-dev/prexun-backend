<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicStudentController extends Controller
{
    /**
     * Register a new student publicly
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email',
            'phone' => 'required|string|max:20',
            'campus_id' => 'required|exists:campuses,id',
            'carrer_id' => 'nullable|exists:carreras,id',
            'facultad_id' => 'nullable|exists:facultades,id',
            'prepa_id' => 'nullable|exists:prepas,id',
            'municipio_id' => 'nullable|exists:municipios,id',
            'tutor_name' => 'nullable|string|max:255',
            'tutor_phone' => 'nullable|string|max:20',
            'tutor_relationship' => 'nullable|string|max:100',
            'average' => 'nullable|numeric|min:0|max:10',
            'health_conditions' => 'nullable|string',
            'how_found_out' => 'nullable|string',
            'preferred_communication' => 'nullable|string|in:email,phone,whatsapp'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate a unique username
            $username = $this->generateUniqueUsername($request->firstname, $request->lastname);
            
            $student = Student::create([
                'username' => $username,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'phone' => $request->phone,
                'campus_id' => $request->campus_id,
                'carrer_id' => $request->carrer_id,
                'facultad_id' => $request->facultad_id,
                'prepa_id' => $request->prepa_id,
                'municipio_id' => $request->municipio_id,
                'tutor_name' => $request->tutor_name,
                'tutor_phone' => $request->tutor_phone,
                'tutor_relationship' => $request->tutor_relationship,
                'average' => $request->average,
                'health_conditions' => $request->health_conditions,
                'how_found_out' => $request->how_found_out,
                'preferred_communication' => $request->preferred_communication ?? 'email',
                'status' => 'pendiente', // Default status for public registrations
                'type' => 'regular'
            ]);

            return response()->json([
                'message' => 'Registro exitoso. Tu solicitud está pendiente de revisión.',
                'student' => [
                    'id' => $student->id,
                    'firstname' => $student->firstname,
                    'lastname' => $student->lastname,
                    'email' => $student->email,
                    'status' => $student->status
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available campuses for registration
     */
    public function getCampuses()
    {
        $campuses = Campus::select('id', 'name', 'city', 'state')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return response()->json($campuses);
    }

    /**
     * Generate a unique username based on first and last name
     */
    private function generateUniqueUsername($firstname, $lastname)
    {
        $baseUsername = Str::slug($firstname . '.' . $lastname, '.');
        $username = $baseUsername;
        $counter = 1;

        while (Student::where('username', $username)->exists()) {
            $username = $baseUsername . '.' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Get registration form data (campuses, careers, etc.)
     */
    public function getFormData()
    {
        try {
            $data = [
                'campuses' => Campus::select('id', 'name', 'city', 'state')
                    ->where('active', true)
                    ->orderBy('name')
                    ->get(),
                'carreras' => \App\Models\Carrera::select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'facultades' => \App\Models\Facultad::select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'prepas' => \App\Models\Prepa::select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'municipios' => \App\Models\Municipio::select('id', 'name')
                    ->orderBy('name')
                    ->get()
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener datos del formulario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}