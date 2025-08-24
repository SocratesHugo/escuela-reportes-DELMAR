<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Week extends Model
{
    protected $table = 'weeks';

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    /**
     * Etiqueta legible para selects y vistas (sin horas).
     * Ej: "Semana 1 — 2025-09-01 a 2025-09-05"
     */
    public function getLabelAttribute(): string
    {
        $name  = $this->name ?? 'Semana';
        $start = $this->starts_at ? Carbon::parse($this->starts_at)->format('Y-m-d') : null;
        $end   = $this->ends_at   ? Carbon::parse($this->ends_at)->format('Y-m-d')   : null;

        if ($start && $end) {
            return "{$name} — {$start} a {$end}";
        }

        return $name;
    }
}
