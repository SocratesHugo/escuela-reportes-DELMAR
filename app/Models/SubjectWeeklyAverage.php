<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectWeeklyAverage extends Model
{
    protected $fillable = [
        'student_id',
        'group_subject_teacher_id',
        'week_id',
        'avg',
        'works_count',
        'scored_count',
        'pendings_count',
        'justified_count',
        'computed_at',
    ];

    protected $casts = [
        'avg' => 'float',
        'computed_at' => 'datetime',
    ];

    public function student()   { return $this->belongsTo(Student::class); }
    public function assignment(){ return $this->belongsTo(GroupSubjectTeacher::class, 'group_subject_teacher_id'); }
    public function week()      { return $this->belongsTo(Week::class); }
}
