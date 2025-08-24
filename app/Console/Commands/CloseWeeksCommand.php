<?php

namespace App\Console\Commands;

use App\Models\AssignmentWeekState;
use App\Models\GroupSubjectTeacher;
use App\Models\Week;
use App\Services\ComputeWeeklyAverages;
use Illuminate\Console\Command;

class CloseWeeksCommand extends Command
{
    protected $signature = 'weeks:auto-close';
    protected $description = 'Cierra y publica automáticamente semanas vencidas y calcula promedios.';

    public function handle(): int
    {
        // Cierra semanas cuyo fin fue <= ayer
        $weeks = Week::whereDate('end_date', '<=', now()->subDay()->toDateString())->get();
        if ($weeks->isEmpty()) {
            $this->info('No hay semanas para cerrar.');
            return self::SUCCESS;
        }

        $assignments = GroupSubjectTeacher::pluck('id');

        foreach ($weeks as $week) {
            foreach ($assignments as $assignmentId) {
                $state = AssignmentWeekState::firstOrCreate(
                    ['group_subject_teacher_id' => $assignmentId, 'week_id' => $week->id],
                    ['is_closed' => false]
                );

                if (! $state->is_closed) {
                    $state->is_closed = true;
                    $state->closed_at = now();
                    $state->save();

                    ComputeWeeklyAverages::run((int) $assignmentId, (int) $week->id);
                }
            }
        }

        $this->info('Semanas cerradas y promedios actualizados.');
        return self::SUCCESS;
    }
}
