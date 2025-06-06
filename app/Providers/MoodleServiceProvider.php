<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Moodle\MoodleService;
use App\Services\Moodle\MoodleUserService;
use App\Services\Moodle\MoodleCohortService;

class MoodleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MoodleService::class, function ($app) {
            return new MoodleService();
        });

        $this->app->singleton(MoodleUserService::class, function ($app) {
            return new MoodleUserService();
        });

        $this->app->singleton(MoodleCohortService::class, function ($app) {
            return new MoodleCohortService();
        });        // Alias para mantener compatibilidad con el servicio original
        $this->app->alias(MoodleService::class, 'moodle');
        
        // Registrar el facade
        $this->app->bind('moodle', function () {
            return new MoodleService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
