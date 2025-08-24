<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    protected $fillable = [
        'name','school_year_id','group_id','subject_id','trimester',
        'threshold_pending','threshold_missing','cadence_days',
        'email_subject','email_body','is_active','last_run_at','next_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function schoolYear(){ return $this->belongsTo(SchoolYear::class); }
    public function group(){ return $this->belongsTo(Group::class); }
    public function subject(){ return $this->belongsTo(Subject::class); }
    public function logs(){ return $this->hasMany(NotificationLog::class); }
}
