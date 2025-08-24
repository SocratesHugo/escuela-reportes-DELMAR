<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationRule;
use App\Services\NotificationRuleEvaluator;

class RunNotificationRules extends Command
{
    protected $signature = 'rules:run-notifications';
    protected $description = 'Evalúa reglas de notificación y envía correos a padres';

    public function handle(NotificationRuleEvaluator $evaluator): int
    {
        $now = now();
        $rules = NotificationRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', $now);
            })
            ->get();

        foreach ($rules as $rule) {
            $this->info("Ejecutando: {$rule->name}");
            $evaluator->run($rule);
        }

        return self::SUCCESS;
    }
}
