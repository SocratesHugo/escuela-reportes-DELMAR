<?php

namespace App\Observers;

use App\Jobs\RecomputeWeekAverages;
use App\Models\GradeEntry;
use App\Models\Work;

class GradeEntryObserver
{
    /**
     * Recalcula promedios cuando se crea o actualiza una calificación.
     */
    public function saved(GradeEntry $grade): void
    {
        $this->recomputeForGrade($grade);
    }

    /**
     * Recalcula promedios cuando se borra una calificación.
     */
    public function deleted(GradeEntry $grade): void
    {
        $this->recomputeForGrade($grade);
    }

    /**
     * (Opcional) si usas soft deletes/restores.
     */
    public function restored(GradeEntry $grade): void
    {
        $this->recomputeForGrade($grade);
    }

    /**
     * Detecta la semana a partir del Work y dispara el Job.
     */
    protected function recomputeForGrade(GradeEntry $grade): void
    {
        // Evita problemas si no hay relación o el work_id está vacío
        $workId = $grade->work_id ?? null;
        if (!$workId) {
            return;
        }

        // Si la relación no está cargada, búscala
        $work = $grade->relationLoaded('work') ? $grade->work : Work::find($workId);
        if (!$work || !$work->week_id) {
            return;
        }

        // Ejecuta inmediatamente el recálculo de esa semana
        RecomputeWeekAverages::dispatchSync((int) $work->week_id);
    }
}
