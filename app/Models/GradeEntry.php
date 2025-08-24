<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeEntry extends Model
{
    protected $table = 'grade_entries';

    protected $fillable = [
    'work_id',
    'student_id',
    'status',
    'score',
    'comment',   // 👈 nuevo
];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => 'normal',
    ];

    public function work()    { return $this->belongsTo(Work::class); }
    public function student() { return $this->belongsTo(Student::class); }

    // Normalizadores
    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = strtoupper($value ?? 'normal');
    }

    public function setScoreAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['score'] = null;
            return;
        }
        $v = max(0, min(10, (float)$value));
        $this->attributes['score'] = number_format($v, 2, '.', '');
    }

    // Valor efectivo para promedios (P=0, J=10)
    public function getEffectiveScoreAttribute(): float
    {
        return match ($this->status) {
            'P' => 0.0,
            'J' => 10.0,
            default => (float) ($this->score ?? 0),
        };
    }

    // Scope útil
    public function scopeForPair($q, int $workId, int $studentId)
    {
        return $q->where('work_id', $workId)->where('student_id', $studentId);
    }
}
