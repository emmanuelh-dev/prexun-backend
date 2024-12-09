<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\api\UsersController;
use App\Http\Controllers\DashboardController;
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
});
