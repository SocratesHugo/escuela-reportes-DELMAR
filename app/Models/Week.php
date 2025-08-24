<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Week extends Model
{
    protected $table = 'weeks';

    protected $fillable = [
        'trimester_id',
        'name',
        'starts_at',
        'ends_at',
        'visible_for_parents',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'visible_for_parents' => 'boolean',
    ];

    /**
     * Etiqueta legible para selects:
     * "Semana 1 — 2025-09-01 - 2025-09-05" (sin horas)
     */
    public function getLabelAttribute(): string
    {
        $name  = $this->name ?? 'Semana';
        $start = $this->starts_at ? Carbon::parse($this->starts_at)->toDateString() : '';
        $end   = $this->ends_at   ? Carbon::parse($this->ends_at)->toDateString()   : '';

        return trim($name . ($start && $end ? " — {$start} - {$end}" : ''));
    }

    /** Helper opcional por si lo quieres usar en vistas */
    public function getPeriodAttribute(): string
    {
        $start = $this->starts_at ? Carbon::parse($this->starts_at)->toDateString() : '';
        $end   = $this->ends_at   ? Carbon::parse($this->ends_at)->toDateString()   : '';
        return $start && $end ? "{$start} - {$end}" : '';
    }

    /* Relaciones que ya tengas (ejemplos):
    public function trimester()
    {
        return $this->belongsTo(Trimester::class);
    }

    public function works()
    {
        return $this->hasMany(Work::class);
    }
    */
}
