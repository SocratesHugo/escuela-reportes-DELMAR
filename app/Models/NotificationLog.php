<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_rule_id','student_id','snapshot','sent_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'sent_at' => 'datetime',
    ];

    public function rule(){ return $this->belongsTo(NotificationRule::class,'notification_rule_id'); }
    public function student(){ return $this->belongsTo(Student::class); }
}
