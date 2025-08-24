<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    protected $fillable = [
        'school_name',
        'logo_path',
        'primary_color',
        'secondary_color',
        'contact_email',
    ];
}
