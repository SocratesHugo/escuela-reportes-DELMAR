<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RecomputeWeekAverages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $weekId;

    public function __construct(int $weekId) {
        $this->weekId = $weekId;
    }

    public function handle(): void
    {
        // Si el comando no existe, salimos sin romper el flujo (log informativo)
        if (!method_exists(Artisan::class, 'all') || !Artisan::has('subject-weekly-averages:recompute')) {
            Log::info('[RecomputeWeekAverages] Comando no disponible: subject-weekly-averages:recompute. week_id='.$this->weekId);
            return;
        }

        try {
            // Intentamos pasar --week (si el comando lo soporta funcionará; si no, cae al catch)
            Artisan::call('subject-weekly-averages:recompute', [
                '--week' => $this->weekId,
            ]);
        } catch (\Throwable $e) {
            // Fallback: sin parámetros (y si tampoco lo soporta, sólo registramos y no fallamos)
            try {
                Artisan::call('subject-weekly-averages:recompute');
            } catch (\Throwable $e2) {
                Log::warning('[RecomputeWeekAverages] No se pudo ejecutar el comando, se omite sin error. week_id=' . $this->weekId, [
                    'error' => $e2->getMessage(),
                ]);
            }
        }
    }
}
