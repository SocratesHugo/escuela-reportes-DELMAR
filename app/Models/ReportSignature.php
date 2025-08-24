<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSignature extends Model
{
    protected $fillable = [
        'parent_id',
        'student_id',
        'week_id',
        'parent_email',
        'ip',
        'user_agent',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function parentUser() { return $this->belongsTo(User::class, 'parent_id'); }
    public function student()    { return $this->belongsTo(Student::class); }
    public function week()       { return $this->belongsTo(Week::class); }
}
