<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoCloseRule extends Model
{
    protected $fillable = [
        'group_subject_teacher_id',
        'weekday',
        'run_time',
        'timezone',
        'is_enabled',
        'last_run_at',
        'close_cutoff',
    ];

    protected $casts = [
        'is_enabled' => 'bool',
        'last_run_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(GroupSubjectTeacher::class, 'group_subject_teacher_id');
    }
}
