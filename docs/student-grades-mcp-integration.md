# Integración de Calificaciones de Estudiantes con MCP

## Resumen

Se ha implementado un sistema completo para obtener y gestionar las calificaciones de estudiantes desde Moodle, con integración total al sistema MCP (Model Context Protocol) para que la IA pueda acceder y presentar esta información de manera inteligente.

## Componentes Creados

### 1. StudentGradesService (`app/Services/StudentGradesService.php`)

Servicio centralizado que maneja toda la lógica de obtención de calificaciones:

#### Métodos principales:

- `getStudentGradesByMatricula(string $matricula)`: Obtiene calificaciones usando la matrícula del estudiante
- `getStudentGradesByPhone(string $phoneNumber)`: Obtiene calificaciones usando el teléfono
- `getStudentGrades(Student $student)`: Método principal que obtiene calificaciones completas

#### Características:

- Integra información de Moodle (cursos, actividades, progreso)
- Combina datos de asignaciones locales (grupos, semanas intensivas)
- Agrega detalles completos del curso (imágenes, fechas, progreso)
- Maneja automáticamente la obtención del moodle_id si falta

### 2. Actualización del MCPServerService

Se agregaron 3 nuevas funciones MCP para calificaciones:

#### `get_student_grades`
- Obtiene calificaciones completas de Moodle por matrícula
- Incluye resumen estadístico automático
- Parámetro: `student_id` (matrícula del estudiante)

#### `get_student_grades_by_phone`
- Obtiene calificaciones usando número de teléfono
- Útil para conversaciones de WhatsApp
- Parámetro: `phone_number`

#### `get_student_academic_info`
- Obtiene información académica básica del sistema local
- Incluye: promedio, intentos, puntaje, carrera, etc.
- Parámetro: `student_id`

#### Helpers de formateo:

- `generateGradesSummary()`: Genera estadísticas de calificaciones
- `formatGradesResponseSpanish()`: Formatea respuestas en español amigable

### 3. Actualización del StudentAssignmentController

Se simplificó el método `getStudentGrades()`:

**Antes:**
- ~150 líneas de código con lógica compleja
- Lógica mezclada y difícil de mantener

**Ahora:**
- ~15 líneas de código
- Usa el servicio centralizado
- Más fácil de mantener y testear

### 4. Nuevos Endpoints de WhatsApp

#### GET `/whatsapp/mcp/student/grades/matricula`
```json
{
  "matricula": "4579"
}
```

Respuesta incluye:
- Información del estudiante
- Cursos con calificaciones
- Progreso por curso
- Actividades por curso
- Resumen estadístico

#### GET `/whatsapp/mcp/student/grades/phone`
```json
{
  "phone_number": "+525512345678"
}
```

Mismo formato de respuesta que por matrícula.

### 5. Integración con IA (AIFunctionService)

La IA ahora puede:
- Detectar cuando un estudiante pregunta por calificaciones
- Ejecutar automáticamente la función MCP apropiada
- Formatear la respuesta en español de manera amigable
- Combinar calificaciones con otra información si se solicita

## Estructura de Datos

### Respuesta de Calificaciones

```json
{
  "success": true,
  "data": {
    "student": {
      "id": 4579,
      "matricula": 4579,
      "firstname": "Juan",
      "lastname": "Pérez",
      "moodle_id": 12345
    },
    "courses_count": 5,
    "grades": [
      {
        "course_id": 254,
        "course_name": "Redacción Indirecta",
        "course_type": "Grupo",
        "carrera_name": "Medicina",
        "course_shortname": "NOV2025 RI SCOF",
        "course_fullname": "Redacción Indirecta",
        "course_image": "https://prexun.com/...",
        "grade": "85.00",
        "rawgrade": 85.00,
        "progress": 75,
        "completed": false,
        "start_date": 1756274400,
        "end_date": 0,
        "last_access": 1759959279,
        "activities": [
          {
            "id": 1234,
            "name": "Tarea 1",
            "type": "mod",
            "module": "assign",
            "grade": "90",
            "rawgrade": 90,
            "max_grade": 100,
            "percentage": "90%"
          }
        ],
        "activities_count": 15,
        "course_grade_details": {
          "max_grade": 100,
          "min_grade": 0,
          "percentage": "85%"
        }
      }
    ],
    "summary": {
      "total_courses": 5,
      "courses_with_grades": 4,
      "completed_courses": 2,
      "in_progress_courses": 2,
      "average_grade": 87.5,
      "courses_list": [...]
    }
  }
}
```

## Flujo de Uso con WhatsApp

### Ejemplo de Conversación:

**Usuario (WhatsApp):** "Hola, mi matrícula es 4579, ¿cuáles son mis calificaciones?"

**Sistema:**
1. AIFunctionService detecta la solicitud
2. Ejecuta `get_student_by_id` con id: "4579"
3. Ejecuta `get_student_grades` con student_id: 4579
4. Formatea la respuesta en español

**Respuesta IA:**
```
Hola Juan Pérez (Matrícula: 4579), aquí están tus calificaciones:

📊 Resumen General:
- Total de Cursos: 5
- Cursos con Calificación: 4
- Cursos Completados: 2 ✅
- Cursos en Progreso: 2 ⏳
- Promedio General: 87.5

📚 Cursos:
1. ✅ Redacción Indirecta
   - Calificación: 85.00
   - Progreso: 75%
   - Actividades: 15

2. ⏳ Comprensión Lectora
   - Calificación: 90.00
   - Progreso: 80%
   - Actividades: 12

¿Te gustaría ver más detalles de algún curso en específico? 😊
```

## Testing

### Probar endpoint directo:
```bash
# Por matrícula
curl -X GET "http://localhost:8000/api/whatsapp/mcp/student/grades/matricula?matricula=4579"

# Por teléfono
curl -X GET "http://localhost:8000/api/whatsapp/mcp/student/grades/phone?phone_number=%2B525512345678"
```

### Probar con IA (WhatsApp):
```bash
curl -X POST "http://localhost:8000/api/whatsapp/mcp/test" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "+525512345678",
    "message": "¿Cuáles son mis calificaciones?",
    "send_response": false
  }'
```

## Beneficios de la Arquitectura

1. **Separación de Responsabilidades:**
   - Controller solo maneja HTTP
   - Service maneja lógica de negocio
   - MCP maneja integración con IA

2. **Reutilización:**
   - El servicio se usa en Controller y MCP
   - No hay duplicación de código

3. **Fácil Testing:**
   - Service puede testearse independientemente
   - MCP puede testearse con datos mock

4. **Mantenibilidad:**
   - Cambios en lógica de Moodle solo afectan Service
   - Cambios en formato de IA solo afectan MCP
   - Controller permanece simple y limpio

5. **Escalabilidad:**
   - Fácil agregar nuevos métodos al Service
   - Fácil agregar nuevas funciones MCP
   - Fácil agregar nuevos endpoints

## Próximos Pasos Sugeridos

1. Agregar caché para calificaciones (evitar llamadas repetidas a Moodle)
2. Implementar webhooks de Moodle para actualización automática
3. Agregar filtros por periodo/fecha en calificaciones
4. Implementar análisis de rendimiento académico
5. Agregar comparativas de calificaciones entre periodos
6. Implementar predicciones de calificaciones usando IA

## Notas Importantes

- El `student_id` en la base de datos es igual a la matrícula
- Siempre se verifica/obtiene el `moodle_id` automáticamente
- Las respuestas están optimizadas para WhatsApp (concisas)
- Todo está en español para mejor experiencia de usuario
- Se incluyen emojis para hacer las respuestas más amigables
