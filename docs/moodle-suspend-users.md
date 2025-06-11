# Suspender/Activar Usuarios en Moodle

## Funcionalidad Agregada

Se ha agregado la funcionalidad para suspender (inactivar) y activar usuarios en Moodle usando la API `core_user_update_users`.

## Métodos Disponibles

### 1. MoodleUserService::suspendUser()

Método para suspender o activar usuarios en Moodle.

```php
use App\Services\Moodle\MoodleService;

$moodleService = new MoodleService();

// Suspender usuarios
$users = [
    [
        'id' => 123,      // ID del usuario en Moodle
        'suspended' => 1  // 1 = suspender, 0 = activar
    ],
    [
        'id' => 124,
        'suspended' => 0  // Activar usuario
    ]
];

$result = $moodleService->users()->suspendUser($users);
// o
$result = $moodleService->suspendUser($users);
```

### 2. Endpoints del API

#### Suspender/Activar múltiples estudiantes

```http
POST /api/students/suspend
Content-Type: application/json
Authorization: Bearer {token}

{
    "student_ids": [1, 2, 3, 4],
    "suspended": true
}
```

#### Suspender/Activar un estudiante individual

```http
PUT /api/students/{id}/suspend
Content-Type: application/json
Authorization: Bearer {token}

{
    "suspended": false
}
```

## Ejemplos de Uso

### Ejemplo 1: Suspender estudiantes específicos

```php
// En un controlador o servicio
$studentIds = [1, 2, 3];
$request = new Request([
    'student_ids' => $studentIds,
    'suspended' => true
]);

$controller = new StudentController($moodleService);
$response = $controller->suspendStudents($request);
```

### Ejemplo 2: Activar un estudiante individual

```php
$request = new Request(['suspended' => false]);
$response = $controller->suspendStudent($request, $studentId);
```

### Ejemplo 3: Usando AJAX desde el frontend

```javascript
// Suspender múltiples estudiantes
fetch('/api/students/suspend', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({
        student_ids: [1, 2, 3],
        suspended: true
    })
})
.then(response => response.json())
.then(data => console.log(data));

// Activar un estudiante
fetch('/api/students/123/suspend', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({
        suspended: false
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

## Estructura de Respuesta

### Respuesta Exitosa

```json
{
    "message": "Estudiantes suspendidos exitosamente",
    "results": {
        "success": [1, 2, 3],
        "errors": []
    }
}
```

### Respuesta con Errores

```json
{
    "message": "Error al suspender estudiantes",
    "results": {
        "success": [1, 2],
        "errors": [
            {
                "student_id": 3,
                "error": "No se pudo obtener el ID de Moodle"
            }
        ]
    }
}
```

## Parámetros de la API de Moodle

El método utiliza la función `core_user_update_users` de Moodle con los siguientes parámetros:

- `id`: ID del usuario en Moodle (requerido)
- `suspended`: 1 para suspender, 0 para activar (requerido)

## Logs

El sistema registra todas las operaciones:

```
[INFO] Starting bulk suspender operation for students: {"student_ids":[1,2,3],"suspended":1}
[INFO] Students successfully suspendedidos in Moodle: {"count":3,"action":"suspender"}
```

## Consideraciones

1. **Validación**: Se valida que todos los estudiantes existan en la base de datos local
2. **Moodle ID**: Se asegura que cada estudiante tenga un `moodle_id` válido
3. **Transacciones**: Usa transacciones de base de datos para consistencia
4. **Logs**: Registra todas las operaciones para debugging
5. **Manejo de Errores**: Maneja errores individuales sin afectar otros estudiantes

## Archivos Modificados

- `app/Services/Moodle/MoodleUserService.php` - Método `suspendUser()`
- `app/Services/Moodle/MoodleService.php` - Método de conveniencia
- `app/Http/Controllers/StudentController.php` - Endpoints `suspendStudents()` y `suspendStudent()`
- `routes/api.php` - Rutas del API
