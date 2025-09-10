<?php

namespace App\Http\Controllers;

use App\Models\Checador;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ChecadorController extends Controller
{
    public function checkIn(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
    
        $today = now()->toDateString();
        $currentTime = now();
      
        $existingChecador = Checador::where('user_id', $request->user_id)
                                   ->where('work_date', $today)
                                   ->first();
    
       
        if ($existingChecador && $existingChecador->check_out_at) {
            $checkOutTime = Carbon::parse($today . ' ' . $existingChecador->check_out_at);
            $timeDifference = $checkOutTime->diffInMinutes($currentTime);
            
            
            if ($timeDifference <= 30) {
                $breakDuration = $timeDifference;
                $existingChecador->update([
                    'break_start_at' => $existingChecador->check_out_at,
                    'break_end_at' => $currentTime->toTimeString(),
                    'break_duration' => $breakDuration,
                    'check_out_at' => null, 
                    'status' => 'present'
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Descanso/lunch registrado automáticamente (' . $breakDuration . ' minutos)',
                    'data' => $existingChecador->fresh(),
                    'break_info' => [
                        'duration_minutes' => $breakDuration,
                        'start_time' => $existingChecador->break_start_at,
                        'end_time' => $currentTime->toTimeString()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Han pasado más de 30 minutos desde la salida. Contacte al administrador.'
                ], 400);
            }
        }
        
        if (!$existingChecador) {
            $checador = Checador::create([
                'user_id' => $request->user_id,
                'work_date' => $today,
                'check_in_at' => $currentTime->toTimeString(),
                'status' => 'present'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Entrada registrada exitosamente',
                'data' => $checador
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Ya existe un registro activo para hoy'
        ], 400);
    }

    public function checkOut(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
    
        $today = now()->toDateString();
        $checador = Checador::where('user_id', $request->user_id)
                           ->where('work_date', $today)
                           ->first();
    
        if (!$checador) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró registro de entrada para hoy'
            ], 404);
        }
    
        if ($checador->check_out_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ya se registró la salida para hoy'
            ], 400);
        }
    
        $checador->update([
            'check_out_at' => now()->toTimeString(),
            'status' => 'checked_out'
        ]);
    
        $checador->calculateHoursWorked();
    
        return response()->json([
            'success' => true,
            'message' => 'Salida registrada exitosamente',
            'data' => $checador->fresh()
        ]);
    }

    public function startBreak(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $today = Carbon::today();
        
        $checador = Checador::where('user_id', $request->user_id)
                           ->where('work_date', $today)
                           ->first();

        if (!$checador || !$checador->check_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'Debe hacer check-in primero'
            ], 400);
        }

        if ($checador->status === 'on_break') {
            return response()->json([
                'success' => false,
                'message' => 'Ya está en descanso'
            ], 400);
        }

        $checador->startBreak();

        return response()->json([
            'success' => true,
            'message' => 'Descanso iniciado',
            'data' => $checador
        ]);
    }

    public function endBreak(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $today = Carbon::today();
        
        $checador = Checador::where('user_id', $request->user_id)
                           ->where('work_date', $today)
                           ->first();

        if (!$checador || $checador->status !== 'on_break') {
            return response()->json([
                'success' => false,
                'message' => 'No está en descanso actualmente'
            ], 400);
        }

        $checador->endBreak();

        return response()->json([
            'success' => true,
            'message' => 'Descanso terminado',
            'data' => $checador
        ]);
    }

    public function markRestDay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'work_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $workDate = $request->work_date ? Carbon::parse($request->work_date) : Carbon::today();
        
        $checador = Checador::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'work_date' => $workDate
            ],
            [
                'status' => 'rest_day',
                'hours_worked' => 0,
                'is_complete_day' => false
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Día de descanso registrado',
            'data' => $checador
        ]);
    }

    public function getDailyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'user_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
        
        $query = Checador::with('user:id,name,email')
                         ->where('work_date', $date);
        
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }
        
        $records = $query->get();
        
        $summary = [
            'fecha' => $date->format('Y-m-d'),
            'total_empleados' => $records->count(),
            'presentes' => $records->where('status', '!=', 'absent')->where('status', '!=', 'rest_day')->count(),
            'en_descanso' => $records->where('status', 'rest_day')->count(),
            'ausentes' => $records->where('status', 'absent')->count(),
            'total_horas_trabajadas' => $records->sum('hours_worked'),
            'dias_completos' => $records->where('is_complete_day', true)->count()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $records,
            'resumen' => $summary
        ]);
    }

    public function getCurrentStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        $today = Carbon::today();
        
        $checador = Checador::where('user_id', $request->user_id)
                           ->where('work_date', $today)
                           ->first();

        if (!$checador) {
            return response()->json([
                'success' => true,
                'status' => 'not_checked_in',
                'message' => 'No ha hecho check-in hoy',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $checador->status,
            'data' => $checador
        ]);
    }
}
