<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    protected $fillable = [
        'from_name',
        'from_email',
        'subject_template',
        'body_template',
        'consolidate_by_parent',
        'include_students',
        'link_expires_days',
    ];

    protected $casts = [
        'consolidate_by_parent' => 'bool',
        'include_students'      => 'bool',
        'link_expires_days'     => 'int',
    ];
}
