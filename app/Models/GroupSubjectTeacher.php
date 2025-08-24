<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSubjectTeacher extends Model
{
    protected $table = 'group_subject_teacher';
    protected $fillable = ['group_id','subject_id','teacher_id','active'];

    public function group()   { return $this->belongsTo(Group::class); }
    public function subject() { return $this->belongsTo(\App\Models\Subject::class); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
}
