<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CarreraController;
use App\Http\Controllers\Api\CashCutController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\CohortController;
use App\Http\Controllers\Api\MoodleCohortController;
use App\Http\Controllers\Api\FacultadController;
use App\Http\Controllers\Api\GastoController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\PrepaController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\RemisionController;
use App\Http\Controllers\Api\SemanaIntensivaController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TeacherGroupController;
use App\Http\Controllers\Api\TeacherAttendanceController;
use App\Http\Controllers\Api\StudentAssignmentController;

use App\Http\Controllers\AttendanceController;




Route::get('/test', function () {
    return response()->json(['message' => 'Hello, world!']);
});
Route::post('/test', function () {
    return response()->json(['message' => 'Hello, world!']);
});
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/invoices', [ChargeController::class, 'all']);
Route::get('/invoice/{id}', [ChargeController::class, 'show']);
Route::get('/uuid_invoice/{uuid}', [ChargeController::class, 'showByUuid']);


Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', [AuthController::class, 'user']);


    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'getData']);

    // Users
    Route::get('/users', [UsersController::class, 'index']);
    Route::post('/users', [UsersController::class, 'create']);
    Route::put('/users/{id}', [UsersController::class, 'update']);
    Route::delete('/users/{id}', [UsersController::class, 'destroy']);

    // Campuses
    Route::get('/campuses', [CampusController::class, 'index']);

    // Add admin
    Route::post('/campuses/add-admin', [CampusController::class, 'addAdmin']);
    Route::post('/campuses', [CampusController::class, 'store']);
    Route::get('/campuses/{id}', [CampusController::class, 'show']);
    Route::put('/campuses/{id}', [CampusController::class, 'update']);
    Route::delete('/campuses/{id}', [CampusController::class, 'destroy']);

    // Students
    Route::get('/students', [StudentController::class, 'index']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::get('/students/export-email-group', [StudentController::class, 'exportCsv']);
    Route::post('/students/sync-module', [StudentController::class, 'syncMoodle']);
    Route::post('/students/sync-modules', [StudentController::class, 'syncStudentModules']);
    Route::get('/students/cohort/{cohort_id}', [StudentController::class, 'getByCohort']);
    Route::put('/students/{id}', [StudentController::class, 'update']);
    Route::get('/student/{student}', [StudentController::class, 'show']);
    Route::delete('/students/{student}', [StudentController::class, 'destroy']);
    Route::post('/students/bulk-destroy', [StudentController::class, 'bulkDestroy']);
    Route::post('/students/bulk-update-semana-intensiva', [StudentController::class, 'bulkUpdateSemanaIntensiva']);
    Route::post('/students/bulk-mark-as-active', [StudentController::class, 'bulkMarkAsActive']);
    Route::post('/students/bulk-mark-as-inactive', [StudentController::class, 'bulkMarkAsInactive']);
    Route::post('/students/suspend', [StudentController::class, 'suspendStudents']);
    Route::post('/students/restore/{id}', [StudentController::class, 'restore']);
    Route::patch('/students/hard-update', [StudentController::class, 'hardUpdate']);
    Route::put('/students/{id}/suspend', [StudentController::class, 'suspendStudent']);

    // Cohortes
    Route::get('/cohortes', [CohortController::class, 'index']);
    Route::post('/cohortes/generate', [CohortController::class, 'generate']);
    Route::post('/cohorts/sync', [CohortController::class, 'syncWithMoodle']);

    //planteles
   
    Route::get('/grupos/{id}/students', [GrupoController::class, 'getStudents']);

    // Asistencia
    Route::post('/asistencias', [AttendanceController::class, 'store']);

    // Moodle Cohorts - Nuevas funcionalidades
    Route::prefix('moodle/cohorts')->group(function () {
        Route::delete('/user', [MoodleCohortController::class, 'removeUserFromCohort']);
        Route::delete('/users/bulk', [MoodleCohortController::class, 'removeUsersFromCohorts']);
        Route::delete('/user/all', [MoodleCohortController::class, 'removeUserFromAllCohorts']);
        Route::post('/users', [MoodleCohortController::class, 'addUsersToCohorts']);
        Route::get('/user/{userId}', [MoodleCohortController::class, 'getUserCohorts']);
    });
    

    // Periods
    Route::get('/periods', [PeriodController::class, 'index']);
    Route::post('/periods', [PeriodController::class, 'store']);
    Route::put('/periods/{id}', [PeriodController::class, 'update']);
    Route::delete('/periods/{id}', [PeriodController::class, 'destroy']);

    // charges
    Route::get('/charges/not-paid', [ChargeController::class, 'notPaid']);
    Route::get('/charges/{campus_id}', [ChargeController::class, 'index']);
    Route::post('/charges', [ChargeController::class, 'store']);
    Route::put('/charges/{id}', [ChargeController::class, 'update']);
    Route::delete('/charges/{id}', [ChargeController::class, 'destroy']);
    Route::post('/charges/import-folios', [ChargeController::class, 'importFolios']);
    Route::put('/charges/{id}/update-folio', [ChargeController::class, 'updateFolio']);

    // Municipios
    Route::get('/municipios', [MunicipioController::class, 'index']);
    Route::post('/municipios', [MunicipioController::class, 'store']);
    Route::put('/municipios/{id}', [MunicipioController::class, 'update']);
    Route::delete('/municipios/{id}', [MunicipioController::class, 'destroy']);

    // Prepas
    Route::get('/prepas', [PrepaController::class, 'index']);
    Route::post('/prepas', [PrepaController::class, 'store']);
    Route::put('/prepas/{id}', [PrepaController::class, 'update']);
    Route::delete('/prepas/{id}', [PrepaController::class, 'destroy']);

    // Facultades
    Route::get('/facultades', [FacultadController::class, 'index']);
    Route::post('/facultades', [FacultadController::class, 'store']);
    Route::put('/facultades/{id}', [FacultadController::class, 'update']);
    Route::delete('/facultades/{id}', [FacultadController::class, 'destroy']);

    // Carreras
    Route::get('/carreras', [CarreraController::class, 'index']);
    Route::post('/carreras', [CarreraController::class, 'store']);
    Route::put('/carreras/{id}', [CarreraController::class, 'update']);
    Route::delete('/carreras/{id}', [CarreraController::class, 'destroy']);

    Route::get('/carreras/{id}/modulos', [CarreraController::class, 'getModulos']);
    Route::post('/carreras/{id}/modulos', [CarreraController::class, 'associateModulos']);
    Route::delete('/carreras/{id}/modulos/{moduloId}', [CarreraController::class, 'dissociateModulo']);

    // Modules
    Route::get('/modulos', [ModuloController::class, 'index']);
    Route::post('/modulos', [ModuloController::class, 'store']);
    Route::put('/modulos/{id}', [ModuloController::class, 'update']);
    Route::delete('/modulos/{id}', [ModuloController::class, 'destroy']);

    // Promociones
    Route::get('/promociones', [PromocionController::class, 'index']);
    Route::post('/promociones', [PromocionController::class, 'store']);
    Route::put('/promociones/{id}', [PromocionController::class, 'update']);
    Route::delete('/promociones/{id}', [PromocionController::class, 'destroy']);

    // Remisions
    Route::get('/remisions', [RemisionController::class, 'index']);
    Route::post('/remisions', [RemisionController::class, 'store']);
    Route::get('/remisions/{id}', [RemisionController::class, 'show']);

    // Grupos
    Route::get('/grupos', [GrupoController::class, 'index']);
    Route::post('/grupos', [GrupoController::class, 'store']);
    Route::put('/grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('/grupos/{id}', [GrupoController::class, 'destroy']);

    // Grupos Semanas Intensivas
    Route::get('/semanas', [SemanaIntensivaController::class, 'index']);
    Route::post('/semanas', [SemanaIntensivaController::class, 'store']);
    Route::put('/semanas/{id}', [SemanaIntensivaController::class, 'update']);
    Route::delete('/semanas/{id}', [SemanaIntensivaController::class, 'destroy']);

    // Gastos
    Route::get('/gastos', [GastoController::class, 'index']);
    Route::post('/gastos', [GastoController::class, 'store']);
    Route::get('/gastos/{id}', [GastoController::class, 'show']);
    Route::put('/gastos/{id}', [GastoController::class, 'update']);
    Route::delete('/gastos/{id}', [GastoController::class, 'destroy']);

    // Rutas para maestros y grupos
    Route::prefix('teacher')->group(function () {
        Route::get('/groups', [TeacherGroupController::class, 'index']);
        Route::get('/groups/{id}', [TeacherGroupController::class, 'getTeacherGroups']);
        Route::post('/groups/assign', [TeacherGroupController::class, 'assignGroups']);
        Route::get('/{id}/groups', [TeacherGroupController::class, 'getTeacherGroups']);
        Route::post('/{id}/groups/assign', [TeacherGroupController::class, 'assignGroups']);
        Route::post('/attendance', [TeacherAttendanceController::class, 'store']);
        Route::get('/attendance/{grupo_id}/{date}', [TeacherAttendanceController::class, 'getAttendance']);
    });
    // Products
    Route::get('/products', [ProductsController::class, 'index']);
    Route::post('/products', [ProductsController::class, 'store']);
    Route::put('/products/{product}', [ProductsController::class, 'update']);
    Route::delete('/products/{product}', [ProductsController::class, 'destroy']);

    // Cards
    Route::get('/cards', [CardController::class, 'index']);
    Route::post('/cards', [CardController::class, 'apiStore']);
    Route::put('/cards/{card}', [CardController::class, 'apiUpdate']);
    Route::delete('/cards/{card}', [CardController::class, 'apiDestroy']);


    // CashCuts
    Route::prefix('caja')->group(function () {
        Route::get('/', [CashCutController::class, 'index']);
        Route::post('/', [CashCutController::class, 'store']);
        Route::get('/current/{campus}', [CashCutController::class, 'current']);
        Route::put('/{cashRegister}', [CashCutController::class, 'update']);
    });
    
    // Student Assignments
    Route::get('/student-assignments', [StudentAssignmentController::class, 'index']);
    Route::post('/student-assignments', [StudentAssignmentController::class, 'store']);
    Route::get('/student-assignments/{id}', [StudentAssignmentController::class, 'show']);
    Route::put('/student-assignments/{id}', [StudentAssignmentController::class, 'update']);
    Route::delete('/student-assignments/{id}', [StudentAssignmentController::class, 'destroy']);
    
    // Student Assignment specialized endpoints
    Route::get('/student-assignments/student/{student_id}', [StudentAssignmentController::class, 'getByStudent']);
    Route::get('/student-assignments/period/{period_id}', [StudentAssignmentController::class, 'getByPeriod']);
    Route::get('/student-assignments/grupo/{grupo_id}', [StudentAssignmentController::class, 'getByGrupo']);
    Route::get('/student-assignments/semana/{semana_intensiva_id}', [StudentAssignmentController::class, 'getBySemanaIntensiva']);
    
    // Student Assignment bulk operations
    Route::post('/student-assignments/bulk', [StudentAssignmentController::class, 'bulkStore']);
    Route::put('/student-assignments/bulk', [StudentAssignmentController::class, 'bulkUpdate']);
    Route::patch('/student-assignments/{id}/toggle-active', [StudentAssignmentController::class, 'toggleActive']);
    
    // Site Settings
    Route::prefix('site-settings')->group(function () {
        Route::get('/', [SiteSettingController::class, 'index']);
        Route::post('/', [SiteSettingController::class, 'store']);
        Route::get('/ui-config', [SiteSettingController::class, 'getUIConfig']);
        Route::get('/group/{group}', [SiteSettingController::class, 'getByGroup']);
        Route::get('/value/{key}', [SiteSettingController::class, 'getValue']);
        Route::get('/{id}', [SiteSettingController::class, 'show']);
        Route::put('/{id}', [SiteSettingController::class, 'update']);
        Route::post('/update-multiple', [SiteSettingController::class, 'updateMultiple']);
        Route::delete('/{id}', [SiteSettingController::class, 'destroy']);
    });
});
