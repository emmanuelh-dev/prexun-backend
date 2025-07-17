<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Prepa;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicStudentController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email',
            'phone' => 'required|string|max:20',
            'birth_date' => 'required|date',
            'campus_id' => 'required|exists:campuses,id',
            'carrer_id' => 'nullable|exists:carreers,id',
            'facultad_id' => 'nullable|exists:facultades,id',
            'prepa_id' => 'nullable|exists:prepas,id',
            'municipio_id' => 'nullable|exists:municipios,id',
            'tutor_name' => 'nullable|string|max:255',
            'tutor_phone' => 'nullable|string|max:20',
            'tutor_relationship' => 'nullable|string|max:100',
            'average' => 'nullable|numeric|between:0,10',
            'attempts' => 'nullable|in:1,2,3,4,5,NA',
            'score' => 'nullable|integer|min:0',
            'health_conditions' => 'nullable|string',
            'how_found_out' => 'nullable|string|max:255',
            'preferred_communication' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        
        // Convert "none" values to null for optional fields
        $optionalFields = ['carrer_id', 'facultad_id', 'prepa_id', 'municipio_id', 'tutor_name', 'tutor_phone', 'tutor_relationship', 'average', 'attempts', 'score', 'health_conditions', 'how_found_out', 'preferred_communication'];
        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === 'none') {
                $data[$field] = null;
            }
        }
        
        // Generate unique username
        $baseUsername = strtolower($data['name'] . $data['last_name']);
        $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername);
        $username = $baseUsername;
        $counter = 1;
        
        while (Student::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        $data['username'] = $username;
        $data['status'] = 'pendiente';
        
        try {
            $student = Student::create($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso. Tu solicitud está pendiente de aprobación.',
                'student' => $student
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro. Inténtalo de nuevo.'
            ], 500);
        }
    }

    public function getFormData()
    {
        try {
            $data = [
                'campuses' => Campus::select('id', 'name')->get(),
                'carreras' => Carrera::select('id', 'name')->get(),
                'facultades' => Facultad::select('id', 'name')->get(),
                'prepas' => Prepa::select('id', 'name')->get(),
                'municipios' => Municipio::select('id', 'name')->get()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los datos del formulario'
            ], 500);
        }
    }

    public function getCampuses()
    {
        try {
            $campuses = Campus::select('id', 'name')->get();
            
            return response()->json([
                'success' => true,
                'data' => $campuses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los campus'
            ], 500);
        }
    }
}