<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    protected $fillable = [
        'group_subject_teacher_id',
        'week_id',
        'name',
        'weekday',
        'active',
    ];

    // Alias que usa el Resource (Materia–Grupo)
    public function assignment()
    {
        return $this->belongsTo(GroupSubjectTeacher::class, 'group_subject_teacher_id');
    }

    // Si quieres mantener también el nombre “real”
    public function groupSubjectTeacher()
    {
        return $this->belongsTo(GroupSubjectTeacher::class, 'group_subject_teacher_id');
    }

    public function week()
    {
        return $this->belongsTo(Week::class);
    }
}