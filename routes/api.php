<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CarreraController;
use App\Http\Controllers\Api\CashCutController;
use App\Http\Controllers\Api\TransactionController;
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
use App\Http\Controllers\ContextController;
use App\Http\Controllers\StudentEventController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\MensajeController;
use Illuminate\Support\Facades\Log;

Route::get('/test', function () {
    return response()->json(['message' => 'Hello, world!']);
});
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::post('/alumnos/calif', function () {
  Log::info('Calificaciones');
  return response()->json(['ok' => true]);
});
// Public student registration routes
Route::post('/public/students/register', [App\Http\Controllers\Api\PublicStudentController::class, 'register']);
Route::get('/public/students/form-data', [App\Http\Controllers\Api\PublicStudentController::class, 'getFormData']);

// WhatsApp routes
Route::prefix('whatsapp')->group(function () {
    Route::post('/send-message', [WhatsAppController::class, 'sendMessage']);
    Route::post('/send-template', [WhatsAppController::class, 'sendTemplateMessage']);
    Route::get('/status', [WhatsAppController::class, 'getStatus']);
    Route::get('/conversation', [WhatsAppController::class, 'getConversation']);
    Route::get('/conversations', [WhatsAppController::class, 'getAllConversations']);
    Route::delete('/conversation', [WhatsAppController::class, 'deleteConversation']);
    
    // Webhook routes (sin middleware de autenticación)
    Route::post('/webhook', [WhatsAppController::class, 'receiveMessage']);
    Route::get('/webhook', [WhatsAppController::class, 'verifyWebhook']);

    // Template routes
    Route::apiResource('templates', TemplateController::class);
});
Route::get('/public/campuses', [App\Http\Controllers\Api\PublicStudentController::class, 'getCampuses']);

Route::get('/invoices', [TransactionController::class, 'all']);
Route::get('/invoice/{id}', [TransactionController::class, 'show']);
Route::get('/uuid_invoice/{uuid}', [TransactionController::class, 'showByUuid']);


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

    //mensajes
    Route::post('/mensajes', [MensajeController::class, 'guardarmensaje']);
    Route::get('/mensajes', [MensajeController::class, 'index']); // NUEVA


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

    // Student Events
    Route::get('/student-events/{studentId}', [StudentEventController::class, 'getStudentEvents']);
    Route::get('/student-events/{studentId}/recent/{limit?}', [StudentEventController::class, 'getRecentStudentEvents']);
    Route::get('/student-events/type/{type}', [StudentEventController::class, 'getEventsByType']);
    Route::get('/student-events/movement', [StudentEventController::class, 'getMovementEvents']);

    // Notes
    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{note}', [NoteController::class, 'show']);
    Route::put('/notes/{note}', [NoteController::class, 'update']);
    Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
    Route::get('/students/{student}/notes', [NoteController::class, 'getStudentNotes']);

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
    Route::get('/charges/not-paid', [TransactionController::class, 'notPaid']);
    Route::get('/charges/{campus_id}', [TransactionController::class, 'index']);
    Route::post('/charges', [TransactionController::class, 'store']);
    Route::put('/charges/{id}', [TransactionController::class, 'update']);
    Route::delete('/charges/{id}', [TransactionController::class, 'destroy']);
    Route::post('/charges/import-folios', [TransactionController::class, 'importFolios']);
    Route::put('/charges/{id}/update-folio', [TransactionController::class, 'updateFolio']);

    // Debts (Adeudos)
    Route::get('/debts', [DebtController::class, 'index']);
    Route::post('/debts', [DebtController::class, 'store']);
    Route::get('/debts/{id}', [DebtController::class, 'show']);
    Route::put('/debts/{id}', [DebtController::class, 'update']);
    Route::delete('/debts/{id}', [DebtController::class, 'destroy']);
    Route::get('/debts/student/{studentId}', [DebtController::class, 'getByStudent']);
    Route::get('/debts/campus/{campusId}', [DebtController::class, 'getByCampus']);
    Route::get('/debts/period/{periodId}', [DebtController::class, 'getByPeriod']);
    Route::get('/debts/assignment/{assignmentId}', [DebtController::class, 'getByAssignment']);
    Route::post('/debts/{id}/update-payment-status', [DebtController::class, 'updatePaymentStatus']);
    Route::get('/debts/overdue/list', [DebtController::class, 'getOverdueDebts']);
    Route::get('/debts/summary/stats', [DebtController::class, 'getDebtSummary']);

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
        Route::get('/student/{student}', [TeacherAttendanceController::class, 'findStudent']);
        Route::post('/attendance/quick', [TeacherAttendanceController::class, 'quickStore']);
        Route::get('/attendance/today/{date}', [TeacherAttendanceController::class, 'getTodayAttendance']);
        Route::put('/attendance/{attendance}', [TeacherAttendanceController::class, 'updateAttendance']);
        Route::get('/attendance/student/{student}/report', [TeacherAttendanceController::class, 'getStudentAttendanceReport']);
        Route::get('/attendance/group/{group}/report', [TeacherAttendanceController::class, 'getGroupAttendanceReport']);
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
    Route::get('/student-assignments/students-by-period/{period_id}', [StudentAssignmentController::class, 'getStudentsByAssignedPeriod']);

    // Student Assignment bulk operations
    Route::post('/student-assignments/bulk', [StudentAssignmentController::class, 'bulkStore']);
    Route::put('/student-assignments/bulk', [StudentAssignmentController::class, 'bulkUpdate']);
    Route::patch('/student-assignments/{id}/toggle-active', [StudentAssignmentController::class, 'toggleActive']);

    // Transaction Dashboard
    Route::prefix('transaction-dashboard')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\TransactionDashboardController::class, 'index']);
        Route::get('/campuses', [App\Http\Controllers\Api\TransactionDashboardController::class, 'getCampuses']);
    });

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

    // Context routes
    Route::prefix('contexts')->group(function () {
        Route::get('/', [ContextController::class, 'index']);
        Route::post('/', [ContextController::class, 'store']);
        Route::get('/{context}', [ContextController::class, 'show']);
        Route::put('/{context}', [ContextController::class, 'update']);
        Route::delete('/{context}', [ContextController::class, 'destroy']);
        Route::post('/by-name', [ContextController::class, 'getByName']);
        Route::get('/{context}/instructions', [ContextController::class, 'getInstructions']);
        Route::post('/{context}/activate', [ContextController::class, 'activate']);
        Route::post('/{context}/deactivate', [ContextController::class, 'deactivate']);
        Route::get('/stats/overview', [ContextController::class, 'getStats']);
        Route::post('/whatsapp/default', [ContextController::class, 'createWhatsAppDefault']);
    });

    // Chat routes
    Route::prefix('chat')->group(function () {
        Route::post('/send', [ChatController::class, 'sendMessage']);
        Route::get('/history', [ChatController::class, 'getHistory']);
        Route::delete('/history', [ChatController::class, 'clearHistory']);
        Route::get('/all-conversations', [ChatController::class, 'getAllConversations']);
        Route::get('/history/{userId}', [ChatController::class, 'getUserHistory']);
        Route::delete('/history/{userId}', [ChatController::class, 'clearUserHistory']);

        // Nuevas rutas para gestión de sesiones
        Route::get('/sessions', [ChatController::class, 'getUserSessions']);
        Route::post('/sessions', [ChatController::class, 'createSession']);
        Route::get('/sessions/{sessionId}', [ChatController::class, 'getSessionHistory']);
        Route::get('/conversations/type/{type}', [ChatController::class, 'getConversationsByType']);
    });

    // WhatsApp Chat Integration routes
    Route::prefix('whatsapp')->group(function () {
        Route::get('/chat/conversations', [App\Http\Controllers\Api\WhatsAppChatController::class, 'getConversations']);
        Route::get('/chat/history/{phoneNumber}', [App\Http\Controllers\Api\WhatsAppChatController::class, 'getHistory']);
        Route::post('/chat/send', [App\Http\Controllers\Api\WhatsAppChatController::class, 'sendMessage']);
        Route::delete('/chat/history/{phoneNumber}', [App\Http\Controllers\Api\WhatsAppChatController::class, 'clearHistory']);
    });
});

