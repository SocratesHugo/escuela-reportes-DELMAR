<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentWeekState extends Model
{
    protected $fillable = [
        'group_subject_teacher_id',
        'week_id',
        'is_closed',
        'closed_at',
    ];

    protected $casts = [
        'is_closed' => 'bool',
        'closed_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(GroupSubjectTeacher::class, 'group_subject_teacher_id');
    }

    public function week()
    {
        return $this->belongsTo(Week::class);
    }
}

