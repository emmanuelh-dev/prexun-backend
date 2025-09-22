<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\Attendance;

/**
 * MCP Server Service - Servidor de Protocolo de Contexto de Modelo
 * Proporciona funciones auxiliares que la IA puede ejecutar dinámicamente
 * para obtener información específica de estudiantes y la institución
 */
class MCPServerService
{
    private array $availableFunctions = [];
    private array $executionContext = [];

    public function __construct()
    {
        $this->initializeAvailableFunctions();
    }

    /**
     * Inicializar funciones disponibles para el MCP Server
     */
    private function initializeAvailableFunctions()
    {
        $this->availableFunctions = [
            'get_student_by_matricula' => [
                'description' => 'Buscar estudiante por matrícula',
                'parameters' => [
                    'matricula' => ['type' => 'string', 'required' => true, 'description' => 'Matrícula del estudiante']
                ]
            ],
            'get_student_by_phone' => [
                'description' => 'Buscar estudiante por número de teléfono',
                'parameters' => [
                    'phone_number' => ['type' => 'string', 'required' => true, 'description' => 'Número de teléfono del estudiante']
                ]
            ],
            'get_student_payments' => [
                'description' => 'Obtener estado de transacciones/pagos de un estudiante',
                'parameters' => [
                    'student_id' => ['type' => 'integer', 'required' => true, 'description' => 'ID del estudiante'],
                    'limit' => ['type' => 'integer', 'required' => false, 'description' => 'Número de transacciones a mostrar (default: 5)']
                ]
            ],
            'get_student_grades' => [
                'description' => 'Obtener información académica de un estudiante (carrera, promedio, etc.)',
                'parameters' => [
                    'student_id' => ['type' => 'integer', 'required' => true, 'description' => 'ID del estudiante']
                ]
            ],
            'get_student_schedule' => [
                'description' => 'Obtener información de grupo y horarios de un estudiante',
                'parameters' => [
                    'student_id' => ['type' => 'integer', 'required' => true, 'description' => 'ID del estudiante']
                ]
            ],
            'get_student_attendance' => [
                'description' => 'Obtener registro de asistencias de un estudiante',
                'parameters' => [
                    'student_id' => ['type' => 'integer', 'required' => true, 'description' => 'ID del estudiante'],
                    'date_from' => ['type' => 'string', 'required' => false, 'description' => 'Fecha inicio (YYYY-MM-DD)'],
                    'date_to' => ['type' => 'string', 'required' => false, 'description' => 'Fecha fin (YYYY-MM-DD)']
                ]
            ],
            'get_student_profile' => [
                'description' => 'Obtener perfil completo de un estudiante',
                'parameters' => [
                    'student_id' => ['type' => 'integer', 'required' => true, 'description' => 'ID del estudiante']
                ]
            ],
            'search_students' => [
                'description' => 'Buscar estudiantes por nombre o criterios',
                'parameters' => [
                    'query' => ['type' => 'string', 'required' => true, 'description' => 'Término de búsqueda'],
                    'limit' => ['type' => 'integer', 'required' => false, 'description' => 'Número máximo de resultados']
                ]
            ]
        ];
    }

    /**
     * Obtener lista de funciones disponibles para la IA
     */
    public function getAvailableFunctions(): array
    {
        return $this->availableFunctions;
    }

    /**
     * Ejecutar una función específica con parámetros
     */
    public function executeFunction(string $functionName, array $parameters = []): array
    {
        try {
            if (!isset($this->availableFunctions[$functionName])) {
                return [
                    'success' => false,
                    'error' => "Función '{$functionName}' no disponible",
                    'available_functions' => array_keys($this->availableFunctions)
                ];
            }

            // Validar parámetros requeridos
            $validation = $this->validateParameters($functionName, $parameters);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => "Parámetros inválidos: {$validation['message']}",
                    'required_parameters' => $this->availableFunctions[$functionName]['parameters']
                ];
            }

            // Ejecutar la función correspondiente
            $result = match($functionName) {
                'get_student_by_matricula' => $this->getStudentByMatricula($parameters['matricula']),
                'get_student_by_phone' => $this->getStudentByPhone($parameters['phone_number']),
                'get_student_payments' => $this->getStudentPayments($parameters['student_id'], $parameters['limit'] ?? 5),
                'get_student_grades' => $this->getStudentGrades($parameters['student_id']),
                'get_student_schedule' => $this->getStudentSchedule($parameters['student_id']),
                'get_student_attendance' => $this->getStudentAttendance(
                    $parameters['student_id'], 
                    $parameters['date_from'] ?? null, 
                    $parameters['date_to'] ?? null
                ),
                'get_student_profile' => $this->getStudentProfile($parameters['student_id']),
                'search_students' => $this->searchStudents($parameters['query'], $parameters['limit'] ?? 10),
                default => ['success' => false, 'error' => 'Función no implementada']
            };

            // Log de la ejecución
            Log::info("MCP Function executed", [
                'function' => $functionName,
                'parameters' => $parameters,
                'success' => $result['success'] ?? false
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Error ejecutando función MCP", [
                'function' => $functionName,
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => "Error interno: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Validar parámetros de una función
     */
    private function validateParameters(string $functionName, array $parameters): array
    {
        $functionDef = $this->availableFunctions[$functionName];
        $requiredParams = [];

        foreach ($functionDef['parameters'] as $paramName => $paramDef) {
            if ($paramDef['required'] && !isset($parameters[$paramName])) {
                $requiredParams[] = $paramName;
            }
        }

        if (!empty($requiredParams)) {
            return [
                'valid' => false,
                'message' => "Parámetros requeridos faltantes: " . implode(', ', $requiredParams)
            ];
        }

        return ['valid' => true];
    }

    /**
     * Normalizar número de teléfono
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^+\d]/', '', $phoneNumber);
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }
        return $cleaned;
    }

    // ============================================
    // FUNCIONES AUXILIARES PARA CONSULTAS
    // ============================================

    /**
     * Buscar estudiante por matrícula
     */
    public function getStudentByMatricula(string $matricula): array
    {
        try {
            $student = Student::where('matricula', $matricula)
                ->orWhere('id', $matricula)
                ->first();

            if (!$student) {
                return [
                    'success' => false,
                    'error' => "No se encontró estudiante con matrícula: {$matricula}"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $student->id,
                    'matricula' => $student->matricula ?? $student->id,
                    'name' => $student->firstname . ' ' . $student->lastname,
                    'firstname' => $student->firstname,
                    'lastname' => $student->lastname,
                    'email' => $student->email,
                    'phone_number' => $student->phone,
                    'carrer_id' => $student->carrer_id,
                    'facultad_id' => $student->facultad_id,
                    'campus_id' => $student->campus_id,
                    'status' => $student->status,
                    'created_at' => $student->created_at
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error buscando estudiante: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Buscar estudiante por número de teléfono
     */
    public function getStudentByPhone(string $phoneNumber): array
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

            return [
                'success' => true,
                'data' => [
                    'id' => $student->id,
                    'matricula' => $student->matricula ?? $student->id,
                    'name' => $student->firstname . ' ' . $student->lastname,
                    'firstname' => $student->firstname,
                    'lastname' => $student->lastname,
                    'email' => $student->email,
                    'phone_number' => $student->phone,
                    'carrer_id' => $student->carrer_id,
                    'facultad_id' => $student->facultad_id,
                    'campus_id' => $student->campus_id,
                    'status' => $student->status
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error buscando estudiante por teléfono: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Obtener estado de transacciones/pagos de un estudiante
     */
    public function getStudentPayments(int $studentId, int $limit = 5): array
    {
        try {
            // Verificar que el estudiante existe
            $student = Student::find($studentId);
            if (!$student) {
                return [
                    'success' => false,
                    'error' => "Estudiante no encontrado"
                ];
            }

            // Obtener transacciones (ajustar según tu estructura de BD)
            $transactions = Transaction::where('student_id', $studentId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            // Calcular estadísticas
            $totalPaid = $transactions->where('paid', true)->sum('amount');
            $totalPending = $transactions->where('paid', false)->sum('amount');
            $lastTransaction = $transactions->first();

            return [
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'student_name' => $student->firstname . ' ' . $student->lastname,
                    'total_paid' => $totalPaid,
                    'total_pending' => $totalPending,
                    'last_transaction' => $lastTransaction ? [
                        'amount' => $lastTransaction->amount,
                        'paid' => $lastTransaction->paid,
                        'payment_date' => $lastTransaction->payment_date,
                        'expiration_date' => $lastTransaction->expiration_date,
                        'transaction_type' => $lastTransaction->transaction_type,
                        'payment_method' => $lastTransaction->payment_method,
                        'notes' => $lastTransaction->notes
                    ] : null,
                    'recent_transactions' => $transactions->map(function($transaction) {
                        return [
                            'id' => $transaction->id,
                            'amount' => $transaction->amount,
                            'paid' => $transaction->paid,
                            'transaction_type' => $transaction->transaction_type,
                            'payment_method' => $transaction->payment_method,
                            'payment_date' => $transaction->payment_date,
                            'expiration_date' => $transaction->expiration_date,
                            'notes' => $transaction->notes
                        ];
                    })
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error obteniendo transacciones: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Obtener información académica de un estudiante
     */
    public function getStudentGrades(int $studentId): array
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return [
                    'success' => false,
                    'error' => "Estudiante no encontrado"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'student_name' => $student->firstname . ' ' . $student->lastname,
                    'academic_info' => [
                        'average' => $student->average,
                        'attempts' => $student->attempts,
                        'score' => $student->score,
                        'career_id' => $student->carrer_id,
                        'faculty_id' => $student->facultad_id,
                        'campus_id' => $student->campus_id,
                        'group_id' => $student->grupo_id,
                        'period_id' => $student->period_id,
                        'status' => $student->status
                    ]
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error obteniendo información académica: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Obtener información de grupo y horarios de un estudiante
     */
    public function getStudentSchedule(int $studentId): array
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return [
                    'success' => false,
                    'error' => "Estudiante no encontrado"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'student_name' => $student->firstname . ' ' . $student->lastname,
                    'group_info' => [
                        'grupo_id' => $student->grupo_id,
                        'semana_intensiva_id' => $student->semana_intensiva_id,
                        'period_id' => $student->period_id,
                        'campus_id' => $student->campus_id
                    ]
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error obteniendo información de grupo: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Obtener registro de asistencias
     */
    public function getStudentAttendance(int $studentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return [
                    'success' => false,
                    'error' => "Estudiante no encontrado"
                ];
            }

            $query = Attendance::where('student_id', $studentId);
            
            if ($dateFrom) {
                $query->where('date', '>=', $dateFrom);
            }
            
            if ($dateTo) {
                $query->where('date', '<=', $dateTo);
            }

            $attendance = $query->orderBy('date', 'desc')->get();
            
            $totalClasses = $attendance->count();
            $presentClasses = $attendance->where('present', true)->count();
            $attendanceRate = $totalClasses > 0 ? ($presentClasses / $totalClasses) * 100 : 0;

            return [
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'student_name' => $student->firstname . ' ' . $student->lastname,
                    'period' => [
                        'from' => $dateFrom ?? 'Inicio',
                        'to' => $dateTo ?? 'Actual'
                    ],
                    'summary' => [
                        'total_classes' => $totalClasses,
                        'present' => $presentClasses,
                        'absent' => $totalClasses - $presentClasses,
                        'attendance_rate' => round($attendanceRate, 2)
                    ],
                    'attendance_records' => $attendance->map(function($record) {
                        return [
                            'date' => $record->date,
                            'present' => $record->present,
                            'attendance_time' => $record->attendance_time,
                            'notes' => $record->notes,
                            'grupo_id' => $record->grupo_id
                        ];
                    })
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error obteniendo asistencias: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Obtener perfil completo del estudiante
     */
    public function getStudentProfile(int $studentId): array
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return [
                    'success' => false,
                    'error' => "Estudiante no encontrado"
                ];
            }

            // Obtener información adicional
            $paymentsResult = $this->getStudentPayments($studentId, 3);
            $gradesResult = $this->getStudentGrades($studentId);
            
            return [
                'success' => true,
                'data' => [
                    'profile' => [
                        'id' => $student->id,
                        'matricula' => $student->matricula ?? $student->id,
                        'name' => $student->firstname . ' ' . $student->lastname,
                        'firstname' => $student->firstname,
                        'lastname' => $student->lastname,
                        'email' => $student->email,
                        'phone' => $student->phone,
                        'carrer_id' => $student->carrer_id,
                        'facultad_id' => $student->facultad_id,
                        'campus_id' => $student->campus_id,
                        'grupo_id' => $student->grupo_id,
                        'status' => $student->status,
                        'tutor_name' => $student->tutor_name,
                        'tutor_phone' => $student->tutor_phone,
                        'average' => $student->average,
                        'enrollment_date' => $student->created_at
                    ],
                    'academic_summary' => $gradesResult['success'] ? [
                        'average' => $gradesResult['data']['academic_info']['average'],
                        'attempts' => $gradesResult['data']['academic_info']['attempts'],
                        'score' => $gradesResult['data']['academic_info']['score']
                    ] : null,
                    'payment_summary' => $paymentsResult['success'] ? [
                        'total_paid' => $paymentsResult['data']['total_paid'],
                        'total_pending' => $paymentsResult['data']['total_pending'],
                        'last_transaction' => $paymentsResult['data']['last_transaction']
                    ] : null
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error obteniendo perfil: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Buscar estudiantes por nombre o criterios
     */
    public function searchStudents(string $query, int $limit = 10): array
    {
        try {
            $students = Student::where('firstname', 'LIKE', "%{$query}%")
                ->orWhere('lastname', 'LIKE', "%{$query}%")
                ->orWhere('matricula', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->limit($limit)
                ->get();

            return [
                'success' => true,
                'data' => [
                    'query' => $query,
                    'total_found' => $students->count(),
                    'students' => $students->map(function($student) {
                        return [
                            'id' => $student->id,
                            'matricula' => $student->matricula ?? $student->id,
                            'name' => $student->firstname . ' ' . $student->lastname,
                            'firstname' => $student->firstname,
                            'lastname' => $student->lastname,
                            'email' => $student->email,
                            'carrer_id' => $student->carrer_id,
                            'facultad_id' => $student->facultad_id,
                            'status' => $student->status
                        ];
                    })
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error buscando estudiantes: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Generar prompt de funciones disponibles para la IA
     */
    public function generateFunctionsPrompt(): string
    {
        $prompt = "Tienes acceso a las siguientes funciones para consultar información de estudiantes:\n\n";
        
        foreach ($this->availableFunctions as $functionName => $definition) {
            $prompt .= "**{$functionName}**: {$definition['description']}\n";
            $prompt .= "Parámetros:\n";
            
            foreach ($definition['parameters'] as $paramName => $paramDef) {
                $required = $paramDef['required'] ? '(requerido)' : '(opcional)';
                $prompt .= "- {$paramName} {$required}: {$paramDef['description']}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Para usar estas funciones, menciona el nombre de la función y los parámetros necesarios en tu respuesta.\n";
        $prompt .= "Ejemplo: 'Necesito usar get_student_by_matricula con matricula: 12345'\n";
        
        return $prompt;
    }
}