<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\Student;
use Illuminate\Http\Request;

class MensajeController extends Controller
{
    public function guardarmensaje(Request $request) 
    {
        //  Validar lo que llega desde el formulario
        $request->validate([
            'nombre' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'student_id' => 'required|integer|exists:students,id',
            'role' => 'required|string',
        ]); 
        // esto asegura que no te guarden datos vacíos o con formatos incorrectos

        //  Guardar en la base de datos
        $message = Mensaje::create([
            'nombre' => $request->nombre,
            'mensaje' => $request->mensaje,
            'student_id' => $request->student_id,
            'role' => $request->role,
        ]); 
        // aquí usas el modelo Mensaje y `create` para insertar directamente

        //  Respuesta al frontend
        return response()->json([
            'success' => true,
            'message' => 'Mensaje guardado correctamente',
            'data' => $message
        ]);
    }
    
    public function index(Request $request)
    {
        $studentId = $request->query('student_id');
        
        if ($studentId) {
            $mensajes = Mensaje::where('student_id', $studentId)
                              ->orderBy('created_at', 'asc')
                              ->get();
        } else {
            $mensajes = Mensaje::orderBy('created_at', 'desc')->get();
        }
        
        return response()->json([
            'success' => true,
            'data' => $mensajes
        ]);
    }
}
