<?php

namespace App\Observers;

use App\Jobs\RecomputeWeekAverages;
use App\Models\Work;

class WorkObserver
{
    /**
     * Al crear un trabajo, recalcula la semana del trabajo.
     */
    public function created(Work $work): void
    {
        $this->dispatchForWeek($work->week_id);
    }

    /**
     * Al actualizar un trabajo, si cambió la semana recalcula ambas; si no,
     * recalcula la misma semana porque pudo cambiar materia, activo, etc.
     */
    public function updated(Work $work): void
    {
        $originalWeekId = (int) $work->getOriginal('week_id');
        $currentWeekId  = (int) $work->week_id;

        if ($originalWeekId !== $currentWeekId) {
            $this->dispatchForWeek($originalWeekId);
            $this->dispatchForWeek($currentWeekId);
        } else {
            $this->dispatchForWeek($currentWeekId);
        }
    }

    /**
     * Al borrar (soft o hard), recalcula la semana de ese trabajo.
     */
    public function deleted(Work $work): void
    {
        $this->dispatchForWeek($work->week_id);
    }

    /**
     * Si usas SoftDeletes, al restaurar vuelve a recalcular.
     */
    public function restored(Work $work): void
    {
        $this->dispatchForWeek($work->week_id);
    }

    /**
     * También por completitud si usas forceDelete().
     */
    public function forceDeleted(Work $work): void
    {
        $this->dispatchForWeek($work->week_id);
    }

    /**
     * Dispara el Job de recálculo inmediatamente (sin colas).
     */
    protected function dispatchForWeek(?int $weekId): void
    {
        if (!$weekId) {
            return;
        }

        // Ejecuta el comando de recálculo de promedios de esa semana
        RecomputeWeekAverages::dispatchSync((int) $weekId);
    }
}
