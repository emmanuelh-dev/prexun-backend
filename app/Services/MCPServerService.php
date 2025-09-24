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
            'get_student_by_id' => [
                'description' => 'Buscar estudiante por ID. Cuando el usuario dice "matrícula", usar ese número como ID',
                'parameters' => [
                    'id' => ['type' => 'string', 'required' => true, 'description' => 'ID del estudiante (cuando el usuario dice "matrícula", usar ese número)']
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
                    'error' => "Función '{$functionName}' no está disponible",
                    'funciones_disponibles' => array_keys($this->availableFunctions)
                ];
            }

            // Validar parámetros requeridos
            $validation = $this->validateParameters($functionName, $parameters);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => "Parámetros inválidos: {$validation['message']}",
                    'parametros_requeridos' => $this->availableFunctions[$functionName]['parameters']
                ];
            }

            // Ejecutar la función correspondiente
            $result = match($functionName) {
                'get_student_by_id' => $this->getStudentById($parameters['id']),
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
                'error' => "Error interno: " . $this->translateErrorToSpanish($e->getMessage())
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

    /**
     * Traducir mensajes de error comunes a español
     */
    private function translateErrorToSpanish(string $errorMessage): string
    {
        $translations = [
            // Errores de base de datos MySQL/PostgreSQL
            'Connection refused' => 'Conexión a la base de datos rechazada',
            'Database connection failed' => 'Falló la conexión a la base de datos',
            'Table doesn\'t exist' => 'La tabla no existe',
            'Column not found' => 'Columna no encontrada',
            'Unknown column' => 'Columna desconocida',
            'Duplicate entry' => 'Entrada duplicada',
            'Foreign key constraint fails' => 'Falla restricción de clave foránea',
            'Data too long for column' => 'Datos demasiado largos para la columna',
            'Access denied' => 'Acceso denegado',
            'Unknown database' => 'Base de datos desconocida',
            'SQLSTATE' => 'Error de SQL',
            
            // Errores de Eloquent/Laravel específicos
            'No query results for model' => 'No se encontraron resultados para el modelo',
            'Call to undefined method' => 'Llamada a método no definido',
            'Class not found' => 'Clase no encontrada',
            'Method not found' => 'Método no encontrado',
            'Property not found' => 'Propiedad no encontrada',
            'Undefined property' => 'Propiedad no definida',
            'Undefined variable' => 'Variable no definida',
            'ModelNotFoundException' => 'Modelo no encontrado',
            'QueryException' => 'Error en la consulta de base de datos',
            
            // Errores de validación
            'Validation failed' => 'Falló la validación',
            'Required field missing' => 'Campo requerido faltante',
            'Invalid format' => 'Formato inválido',
            'Invalid data type' => 'Tipo de dato inválido',
            'The given data was invalid' => 'Los datos proporcionados son inválidos',
            
            // Errores de red/API
            'Connection timeout' => 'Tiempo de conexión agotado',
            'Network error' => 'Error de red',
            'Server error' => 'Error del servidor',
            'Service unavailable' => 'Servicio no disponible',
            'cURL error' => 'Error de conexión',
            'HTTP error' => 'Error HTTP',
            
            // Errores de PHP comunes
            'Fatal error' => 'Error fatal',
            'Parse error' => 'Error de análisis',
            'Memory limit exceeded' => 'Límite de memoria excedido',
            'Maximum execution time exceeded' => 'Tiempo máximo de ejecución excedido',
            'Division by zero' => 'División por cero',
            'Array to string conversion' => 'Error de conversión de array a string',
            
            // Errores de autenticación y permisos
            'Unauthenticated' => 'No autenticado',
            'Unauthorized' => 'No autorizado',
            'Forbidden' => 'Prohibido',
            'Permission denied' => 'Permiso denegado'
        ];

        $lowerErrorMessage = strtolower($errorMessage);
        
        foreach ($translations as $english => $spanish) {
            if (stripos($lowerErrorMessage, strtolower($english)) !== false) {
                return $spanish;
            }
        }

        // Si no hay traducción específica, devolver el mensaje original
        return $errorMessage;
    }

    // ============================================
    // FUNCIONES AUXILIARES PARA CONSULTAS
    // ============================================

    /**
     * Buscar estudiante por ID
     * IMPORTANTE: Cuando el usuario dice "mi matrícula es 4579", usar 4579 como ID
     * La "matrícula" que menciona el usuario es realmente el ID en la base de datos
     */
    public function getStudentById(string $id): array
    {
        try {
            $student = Student::find($id);

            if (!$student) {
                return [
                    'success' => false,
                    'error' => "No se encontró estudiante con ID: {$id}"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $student->id,
                    'matricula' => $student->id, // La matrícula es igual al ID
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
                'error' => "Error buscando estudiante: " . $this->translateErrorToSpanish($e->getMessage())
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
                    'matricula' => $student->id, // La matrícula es igual al ID
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
                'error' => "Error buscando estudiante por teléfono: " . $this->translateErrorToSpanish($e->getMessage())
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
                'error' => "Error obteniendo transacciones: " . $this->translateErrorToSpanish($e->getMessage())
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
                'error' => "Error obteniendo información académica: " . $this->translateErrorToSpanish($e->getMessage())
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
                'error' => "Error obteniendo información de grupo: " . $this->translateErrorToSpanish($e->getMessage())
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
                'error' => "Error obteniendo asistencias: " . $this->translateErrorToSpanish($e->getMessage())
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
                        'matricula' => $student->id, // La matrícula es igual al ID
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
                'error' => "Error obteniendo perfil: " . $this->translateErrorToSpanish($e->getMessage())
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
                ->orWhere('id', 'LIKE', "%{$query}%")
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
                            'matricula' => $student->id, // La matrícula es igual al ID
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
                'error' => "Error buscando estudiantes: " . $this->translateErrorToSpanish($e->getMessage())
            ];
        }
    }

    /**
     * Generar prompt de funciones disponibles para la IA
     */
    public function generateFunctionsPrompt(): string
    {
        $prompt = "INSTRUCCIONES IMPORTANTES:\n";
        $prompt .= "- SIEMPRE responde en ESPAÑOL, sin excepción\n";
        $prompt .= "- Usa un tono amable y profesional\n";
        $prompt .= "- Cuando el usuario mencione 'matrícula', usar ese número como 'id' para buscar\n";
        $prompt .= "- Presenta la información de manera clara y organizada\n";
        $prompt .= "- Usa formato de lista con viñetas para facilitar la lectura\n\n";
        
        $prompt .= "FUNCIONES DISPONIBLES:\n\n";
        
        foreach ($this->availableFunctions as $functionName => $definition) {
            $prompt .= "**{$functionName}**: {$definition['description']}\n";
            $prompt .= "Parámetros:\n";
            
            foreach ($definition['parameters'] as $paramName => $paramDef) {
                $required = $paramDef['required'] ? '(requerido)' : '(opcional)';
                $prompt .= "- {$paramName} {$required}: {$paramDef['description']}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "EJEMPLOS DE USO:\n";
        $prompt .= "- Usuario: 'Mi matrícula es 4054, ¿cuáles son mis pagos?'\n";
        $prompt .= "- Acción: Usar get_student_by_id con id: '4054', luego get_student_payments\n";
        $prompt .= "- Respuesta: Presentar información EN ESPAÑOL con formato amigable\n\n";
        
        $prompt .= "FORMATO DE RESPUESTA REQUERIDO:\n";
        $prompt .= "- Saludo personalizado usando el nombre del estudiante\n";
        $prompt .= "- Información organizada con viñetas o numeración\n";
        $prompt .= "- Valores monetarios en formato peso mexicano (\$X,XXX.XX)\n";
        $prompt .= "- Fechas en formato español (15 de septiembre de 2025)\n";
        $prompt .= "- Cierre amable ofreciendo ayuda adicional\n";
        $prompt .= "- TODO EN ESPAÑOL - NUNCA en inglés\n\n";
        
        return $prompt;
    }

    /**
     * Generar instrucciones específicas para forzar respuestas en español
     */
    public function getSpanishResponseInstructions(): array
    {
        return [
            'language' => 'es',
            'instructions' => [
                'OBLIGATORIO: Toda respuesta debe ser en español',
                'Usar tono amable y profesional',
                'Saludar al estudiante por su nombre cuando sea posible',
                'Presentar información de forma organizada y clara',
                'Usar formato de listas para facilitar lectura',
                'Incluir valores monetarios en pesos mexicanos',
                'Fechas en formato español (día de mes de año)',
                'Finalizar ofreciendo ayuda adicional',
                'NUNCA responder en inglés - siempre en español'
            ],
            'examples' => [
                'greeting' => 'Hola [Nombre], aquí tienes la información solicitada:',
                'currency' => '$1,500.00 pesos mexicanos',
                'date' => '15 de septiembre de 2025',
                'closing' => '¿Te puedo ayudar con algo más?'
            ]
        ];
    }

    /**
     * Formatear información de pagos para respuesta en español
     */
    public function formatPaymentResponseSpanish(array $paymentData): string
    {
        $student = $paymentData['student_name'] ?? 'Estudiante';
        $studentId = $paymentData['student_id'] ?? 'N/A';
        
        $response = "Hola **{$student}** (Matrícula: {$studentId}), aquí tienes la información de tus pagos:\n\n";
        
        // Resumen financiero
        $response .= "## 💰 Resumen Financiero:\n";
        $response .= "- **Total Pagado:** \${$paymentData['total_paid']} pesos mexicanos\n";
        $response .= "- **Total Pendiente:** \${$paymentData['total_pending']} pesos mexicanos\n\n";
        
        // Última transacción
        if (!empty($paymentData['last_transaction'])) {
            $lastTx = $paymentData['last_transaction'];
            $response .= "## 📋 Última Transacción:\n";
            $response .= "- **Monto:** \${$lastTx['amount']} pesos\n";
            $response .= "- **Estado:** " . ($lastTx['paid'] ? 'Pagado ✅' : 'Pendiente ⏳') . "\n";
            
            if ($lastTx['payment_date']) {
                $response .= "- **Fecha de Pago:** {$this->formatDateSpanish($lastTx['payment_date'])}\n";
            }
            if ($lastTx['expiration_date']) {
                $response .= "- **Fecha de Vencimiento:** {$this->formatDateSpanish($lastTx['expiration_date'])}\n";
            }
            if ($lastTx['transaction_type']) {
                $response .= "- **Tipo:** {$this->translateTransactionType($lastTx['transaction_type'])}\n";
            }
            if ($lastTx['payment_method']) {
                $response .= "- **Método de Pago:** {$this->translatePaymentMethod($lastTx['payment_method'])}\n";
            }
            $response .= "\n";
        }
        
        // Transacciones recientes
        if (!empty($paymentData['recent_transactions'])) {
            $response .= "## 📊 Transacciones Recientes:\n";
            foreach ($paymentData['recent_transactions'] as $index => $tx) {
                $response .= ($index + 1) . ". **ID:** {$tx['id']} - **Monto:** \${$tx['amount']} - ";
                $response .= "**Estado:** " . ($tx['paid'] ? 'Pagado ✅' : 'Pendiente ⏳') . "\n";
            }
            $response .= "\n";
        }
        
        $response .= "¿Te puedo ayudar con alguna otra información? 😊";
        
        return $response;
    }

    /**
     * Formatear fecha en español
     */
    private function formatDateSpanish(string $date): string
    {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        
        $timestamp = strtotime($date);
        $day = date('j', $timestamp);
        $month = $months[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        
        return "{$day} de {$month} de {$year}";
    }

    /**
     * Traducir tipos de transacción
     */
    private function translateTransactionType(string $type): string
    {
        $types = [
            'payment' => 'Pago',
            'enrollment' => 'Inscripción',
            'tuition' => 'Colegiatura',
            'fee' => 'Cuota',
            'penalty' => 'Recargo',
            'refund' => 'Reembolso'
        ];
        
        return $types[strtolower($type)] ?? ucfirst($type);
    }

    /**
     * Traducir métodos de pago
     */
    private function translatePaymentMethod(string $method): string
    {
        $methods = [
            'transfer' => 'Transferencia',
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'check' => 'Cheque',
            'online' => 'Pago en línea',
            'bank_deposit' => 'Depósito bancario'
        ];
        
        return $methods[strtolower($method)] ?? ucfirst($method);
    }

    /**
     * Generar contexto completo para la IA incluyendo instrucciones de idioma
     */
    public function generateAIContext(): array
    {
        return [
            'functions_available' => $this->getAvailableFunctions(),
            'instructions' => $this->generateFunctionsPrompt(),
            'language_rules' => $this->getSpanishResponseInstructions(),
            'system_message' => 'Eres un asistente educativo que ayuda a estudiantes con información académica y de pagos. SIEMPRE respondes en español de manera amable y profesional.'
        ];
    }
}