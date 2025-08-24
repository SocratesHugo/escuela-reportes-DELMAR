<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReportSignature extends Model
{
    protected $fillable = [
         'student_id',
        'week_id',
        'parent_name',
        'parent_email',
        'signed_at',
        'ip',
        'user_agent',

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function week()    { return $this->belongsTo(Week::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function parent()  { return $this->belongsTo(User::class, 'parent_id'); }
}
