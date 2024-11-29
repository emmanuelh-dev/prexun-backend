<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\api\UsersController;
use App\Http\Controllers\RegisterUserController;
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
});