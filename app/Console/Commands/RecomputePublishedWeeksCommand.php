<?php

namespace App\Console\Commands;

use App\Models\AssignmentWeekState;
use App\Services\ComputeWeeklyAverages;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RecomputePublishedWeeksCommand extends Command
{
    protected $signature = 'weeks:recompute-published {--days=45 : Recalcular solo semanas de los últimos N días}';
    protected $description = 'Recalcula promedios para TODAS las semanas publicadas (visible a papás/alumnos).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cut  = CarbonImmutable::now()->subDays($days)->toDateString();

        // Busca assignment-week publicados (is_closed = true) con filtro de frescura
        $states = AssignmentWeekState::query()
            ->where('is_closed', true)
            ->whereHas('week', fn($q) => $q->whereDate('end_date', '>=', $cut))
            ->get(['group_subject_teacher_id', 'week_id']);

        $count = 0;
        foreach ($states as $st) {
            ComputeWeeklyAverages::run((int) $st->group_subject_teacher_id, (int) $st->week_id);
            $count++;
        }

        $this->info("Recalculadas {$count} combinaciones materia–grupo/semana (últimos {$days} días).");
        return self::SUCCESS;
    }
}
