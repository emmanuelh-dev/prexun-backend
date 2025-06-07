<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;

class AttendanceController extends Controller
{
    public function store(Request $request)
    {    //valida las solicitudes
        $data = $request->validate([
            'grupo_id' => 'required|integer',
            'fecha' => 'required|date',
            'asistencias' => 'required|array'
        ]);
        //aqui guarda o actualiza asistencia
        foreach ($data['asistencias'] as $asistencia) {
            Asistencia::updateOrCreate(
                [
                    'grupo_id' => $data['grupo_id'],
                    'fecha' => $data['fecha'],
                    'student_id' => $asistencia['student_id']
                ],
                ['status' => $asistencia['status']]
            );
        }
        return response()->json(['message' => 'Asistencias guardadas correctamente']);
    }
    //
}
