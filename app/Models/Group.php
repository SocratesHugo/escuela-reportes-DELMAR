<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'grade',
        'school_year_id',
        'active',
        'titular_id', // <- importante si vas a asignar por mass assignment
    ];

    // Ciclo escolar
    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }

    // Titular del grupo (usuario con rol "titular")
   public function titular() { return $this->belongsTo(\App\Models\User::class, 'titular_id'); }

    // Trabaja con asignaciones materia↔grupo↔maestro
    public function assignments()
    {
        return $this->hasMany(GroupSubjectTeacher::class);
    }

    // Si necesitas a los alumnos del grupo para el filtro de preceptor:
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    // Scopes
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }
    public function homeroom()
{
    return $this->hasOne(\App\Models\Homeroom::class);
}
}
