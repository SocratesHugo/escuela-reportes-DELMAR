<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreceptorStudent extends Model
{
    protected $table = 'preceptor_student';
    protected $fillable = ['preceptor_id','student_id'];

    public function preceptor(){ return $this->belongsTo(User::class,'preceptor_id'); }
    public function student(){ return $this->belongsTo(Student::class); }
}
