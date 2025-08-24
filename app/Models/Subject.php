<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    // Permitimos asignación masiva de estos campos
    protected $fillable = ['name', 'grade', 'school_year_id'];

    // Relación: cada materia pertenece a un ciclo
    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }
    public function assignments() { return $this->hasMany(GroupSubjectTeacher::class); }


}
