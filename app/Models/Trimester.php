<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trimester extends Model
{
    protected $fillable = ['school_year_id','name','starts_at','ends_at'];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];

    public function schoolYear(){ return $this->belongsTo(SchoolYear::class); }
    public function weeks(){ return $this->hasMany(Week::class); }
}
