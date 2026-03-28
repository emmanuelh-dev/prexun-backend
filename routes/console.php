<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Tarea automática de sincronización de calificaciones con Moodle (todas las noches)
Schedule::command('grades:sync')->dailyAt('00:00');
