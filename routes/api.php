<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\CarreraController;
use App\Http\Controllers\Api\ChargeController;
use App\Http\Controllers\Api\FacultadController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\PrepaController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\RegisterUserController;
use App\Http\Controllers\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/test', function () {
    return response()->json(['message' => 'Hello, world!']);
});
Route::post('/test', function () {
    return response()->json(['message' => 'Hello, world!']);
});
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);


Route::middleware(['auth:sanctum'])->group(function () {

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
    Route::get('/students/{campus_id}', [StudentController::class, 'index']);
    Route::get('/students/cohort/{cohort_id}', [StudentController::class, 'getByCohort']);
    Route::post('/students', [StudentController::class, 'store']);
    Route::put('/students/{id}', [StudentController::class, 'update']);
    Route::delete('/students/{id}', [StudentController::class, 'destroy']);
    Route::post('/students/restore/{id}', [StudentController::class, 'restore']);
    Route::post('/students/import', [StudentController::class, 'import']);

    // Periods
    Route::get('/periods', [PeriodController::class, 'index']);
    Route::post('/periods', [PeriodController::class, 'store']);
    Route::put('/periods/{id}', [PeriodController::class, 'update']);
    Route::delete('/periods/{id}', [PeriodController::class, 'destroy']);

    // charges
    Route::get('/charges', [ChargeController::class, 'index']);
    Route::post('/charges', [ChargeController::class, 'store']);
    Route::get('/charges/{id}', [ChargeController::class, 'show']);
    Route::put('/charges/{id}', [ChargeController::class, 'update']);
    Route::delete('/charges/{id}', [ChargeController::class, 'destroy']);

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

    // Modules
    Route::get('/modulos', [ModuloController::class, 'index']);
    Route::post('/modulos', [ModuloController::class, 'store']);
    Route::put('/modulos/{id}', [ModuloController::class, 'update']); 
    Route::delete('/modulos/{id}', [ModuloController::class, 'destroy']);
});
