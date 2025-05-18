<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    // Ruta para maestros
    Route::get('/profesores', function () {
        if (Auth::user()->role !== 'maestro' && Auth::user()->role !== 'teacher') {
            return redirect('/dashboard');
        }
        return view('profesores.index');
    })->name('profesores');
});
