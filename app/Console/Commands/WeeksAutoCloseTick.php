<?php

namespace App\Console\Commands;

use App\Models\AutoCloseRule;
use App\Models\AssignmentWeekState;
use App\Models\GroupSubjectTeacher;
use App\Models\Week;
use App\Services\ComputeWeeklyAverages;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WeeksAutoCloseTick extends Command
{
    protected $signature = 'weeks:auto-close-tick';
    protected $description = 'Ejecuta reglas de cierre automático definidas en la BD (CRUD).';

    public function handle(): int
    {
        AutoCloseRule::where('is_enabled', true)
            ->orderBy('id')
            ->chunk(100, function ($rules) {
                foreach ($rules as $rule) {
                    $tz = $rule->timezone ?: 'America/Mazatlan';
                    $nowTz = CarbonImmutable::now($tz);

                    $shouldRunToday = ((int) $nowTz->isoWeekday() === (int) $rule->weekday);
                    $ruleMinute = CarbonImmutable::parse($rule->run_time, $tz)
                        ->setDate($nowTz->year, $nowTz->month, $nowTz->day);

                    if (! $shouldRunToday || $nowTz->format('Y-m-d H:i') !== $ruleMinute->format('Y-m-d H:i')) {
                        continue;
                    }

                    // evitar doble ejecución en el mismo minuto
                    if ($rule->last_run_at && $rule->last_run_at->timezone($tz)->format('Y-m-d H:i') === $nowTz->format('Y-m-d H:i')) {
                        continue;
                    }

                    $this->runRule($rule, $tz);

                    $rule->last_run_at = $nowTz;
                    $rule->save();
                }
            });

        $this->info('Tick ejecutado.');
        return self::SUCCESS;
    }

    protected function runRule(AutoCloseRule $rule, string $tz): void
    {
        $cutoffDate = match ($rule->close_cutoff) {
            'today'     => CarbonImmutable::now($tz)->toDateString(),
            'yesterday' => CarbonImmutable::now($tz)->subDay()->toDateString(),
            default     => CarbonImmutable::now($tz)->subDay()->toDateString(),
        };

        $weeks = Week::whereDate('end_date', '<=', $cutoffDate)->get();
        if ($weeks->isEmpty()) return;

        $assignments = $rule->group_subject_teacher_id
            ? collect([$rule->group_subject_teacher_id])
            : GroupSubjectTeacher::pluck('id');

        foreach ($weeks as $week) {
            foreach ($assignments as $assignmentId) {
                DB::transaction(function () use ($assignmentId, $week) {
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
                });
            }
        }
    }
}
