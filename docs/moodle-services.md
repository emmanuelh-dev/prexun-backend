# Servicios de Moodle - Documentación

## Estructura Refactorizada

La funcionalidad de Moodle ha sido refactorizada en una estructura más mantenible:

### Servicios Disponibles

1. **BaseMoodleService** - Servicio base abstracto con funcionalidades comunes
2. **MoodleUserService** - Operaciones relacionadas con usuarios
3. **MoodleCohortService** - Operaciones relacionadas con cohorts
4. **MoodleService** - Servicio principal que coordina los otros

## Uso

### Opción 1: Usando el servicio principal (recomendado)

```php
use App\Services\Moodle\MoodleService;

$moodle = new MoodleService();

// Operaciones de usuarios
$user = $moodle->getUserByUsername('john_doe');
$moodle->createUser($userData);
$moodle->deleteUser($userId);

// Operaciones de cohorts
$cohortId = $moodle->getCohortIdByName('Estudiantes 2024');
$moodle->addUserToCohort($members);
$moodle->removeUsersFromCohorts($members);

// O usando los servicios específicos
$user = $moodle->users()->getUserByUsername('john_doe');
$cohorts = $moodle->cohorts()->getUserCohorts($userId);
```

### Opción 2: Usando servicios específicos

```php
use App\Services\Moodle\MoodleUserService;
use App\Services\Moodle\MoodleCohortService;

$userService = new MoodleUserService();
$cohortService = new MoodleCohortService();

// Operaciones específicas
$user = $userService->getUserByUsername('john_doe');
$cohorts = $cohortService->getUserCohorts($userId);
```

## Nueva Funcionalidad: Eliminar Múltiples Usuarios de Cohorts

### removeUsersFromCohorts()

Esta nueva función permite eliminar múltiples usuarios de múltiples cohorts en una sola llamada API.

```php
$members = [
    [
        'userid' => 123,
        'cohortid' => 456
    ],
    [
        'userid' => 124,
        'cohortid' => 456
    ],
    [
        'userid' => 125,
        'cohortid' => 457
    ]
];

$result = $moodle->removeUsersFromCohorts($members);
// o
$result = $moodle->cohorts()->removeUsersFromCohorts($members);
```

### Ventajas de la Nueva Estructura

1. **Separación de responsabilidades**: Cada servicio tiene una responsabilidad específica
2. **Mantenibilidad**: Es más fácil encontrar y modificar funciones específicas
3. **Testabilidad**: Se pueden testear servicios individuales
4. **Extensibilidad**: Fácil agregar nuevos servicios (ej: MoodleCourseService)
5. **Reutilización**: Los servicios pueden usarse independientemente

### Migración desde el Servicio Original

El servicio principal (`MoodleService`) mantiene compatibilidad con la API original, por lo que puedes migrar gradualmente:

```php
// Antes
$moodle = new Moodle();
$user = $moodle->getUserByUsername('john_doe');

// Después (mantiene compatibilidad)
$moodle = new MoodleService();
$user = $moodle->getUserByUsername('john_doe');

// O usando la nueva estructura
$user = $moodle->users()->getUserByUsername('john_doe');
```

## Futuras Extensiones

Fácilmente puedes agregar nuevos servicios:

- `MoodleCourseService` - Para operaciones de cursos
- `MoodleGradeService` - Para operaciones de calificaciones
- `MoodleEnrollmentService` - Para operaciones de inscripciones

Cada servicio extendería `BaseMoodleService` y se registraría en `MoodleServiceProvider`.

## Uso del Facade

Para mayor comodidad, puedes usar el facade de Moodle:

```php
use App\Facades\Moodle;

// Operaciones directas
$user = Moodle::getUserByUsername('john_doe');
$cohorts = Moodle::getUserCohorts($userId);

// Usando servicios específicos
$user = Moodle::users()->getUserByUsername('john_doe');
$result = Moodle::cohorts()->removeUsersFromCohorts($members);
```

## Comandos de Artisan

Se incluye un comando de Artisan para gestionar cohorts desde la línea de comandos:

```bash
# Eliminar un usuario de un cohort específico
php artisan moodle:cohort remove-user --user-id=123 --cohort-id=456

# Eliminar múltiples usuarios de múltiples cohorts
php artisan moodle:cohort remove-users --members='[{"userid":123,"cohortid":456},{"userid":124,"cohortid":457}]'

# Eliminar un usuario de todos sus cohorts
php artisan moodle:cohort remove-all --username=john_doe

# Agregar usuarios a cohorts
php artisan moodle:cohort add-users --members='[{"userid":123,"cohortid":456}]'

# Listar cohorts de un usuario
php artisan moodle:cohort list-user-cohorts --username=john_doe
php artisan moodle:cohort list-user-cohorts --user-id=123
```

## API Endpoints Disponibles

```
DELETE /api/moodle/cohorts/user
Body: {"user_id": 123, "cohort_id": 456}

DELETE /api/moodle/cohorts/users/bulk
Body: {"members": [{"userid": 123, "cohortid": 456}, {"userid": 124, "cohortid": 457}]}

DELETE /api/moodle/cohorts/user/all
Body: {"username": "john_doe"}

POST /api/moodle/cohorts/users
Body: {"members": [{"userid": 123, "cohortid": 456}]}

GET /api/moodle/cohorts/user/{userId}
```

## Ejemplos de Casos de Uso Reales

### 1. Migración de Estudiantes entre Períodos

```php
public function migrateStudentsToPeriod($studentIds, $newPeriodCohortId)
{
    $moodle = new MoodleService();
    
    // 1. Obtener cohorts actuales de cada estudiante
    $currentCohorts = [];
    foreach ($studentIds as $studentId) {
        $cohorts = $moodle->cohorts()->getUserCohorts($studentId);
        if ($cohorts['status'] === 'success') {
            $currentCohorts[$studentId] = $cohorts['data']['cohorts'];
        }
    }
    
    // 2. Eliminar de cohorts de período anterior
    $membersToRemove = [];
    foreach ($currentCohorts as $studentId => $cohorts) {
        foreach ($cohorts as $cohort) {
            if (str_contains($cohort['name'], 'Período')) { // Filtrar cohorts de período
                $membersToRemove[] = [
                    'userid' => $studentId,
                    'cohortid' => $cohort['id']
                ];
            }
        }
    }
    
    if (!empty($membersToRemove)) {
        $moodle->cohorts()->removeUsersFromCohorts($membersToRemove);
    }
    
    // 3. Agregar al nuevo período
    $membersToAdd = [];
    foreach ($studentIds as $studentId) {
        $membersToAdd[] = [
            'userid' => $studentId,
            'cohortid' => $newPeriodCohortId
        ];
    }
    
    return $moodle->cohorts()->addUserToCohort($membersToAdd);
}
```

### 2. Limpieza Masiva de Cohorts

```php
public function cleanupInactiveCohorts($inactiveCohortIds)
{
    $moodle = new MoodleService();
    $allMembersToRemove = [];
    
    foreach ($inactiveCohortIds as $cohortId) {
        // Obtener todos los usuarios del cohort (necesitarías implementar este método)
        $cohortUsers = $this->getCohortUsers($cohortId);
        
        foreach ($cohortUsers as $userId) {
            $allMembersToRemove[] = [
                'userid' => $userId,
                'cohortid' => $cohortId
            ];
        }
    }
    
    // Eliminar todos los usuarios de todos los cohorts inactivos en una sola operación
    return $moodle->cohorts()->removeUsersFromCohorts($allMembersToRemove);
}
```
