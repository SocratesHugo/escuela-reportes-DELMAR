<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GradeEntry;
use App\Observers\GradeEntryObserver;
use App\Models\Work;
use App\Observers\WorkObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Observador: recálculo automático al crear/editar/borrar calificaciones
        GradeEntry::observe(GradeEntryObserver::class);
        Work::observe(WorkObserver::class);

    }
}
