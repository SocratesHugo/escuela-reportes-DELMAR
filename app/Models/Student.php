<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    /**
     * Campos asignables en masa.
     */
    protected $fillable = [
        'paternal_lastname',
        'maternal_lastname',
        'names',
        'email',
        'school_year_id',
        'group_id',
        'active',
    ];

    /**
     * Casts típicos.
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Atributos agregados al array/json del modelo.
     * (Para poder usar $student->full_name directamente en respuestas JSON)
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * ---------------- Relaciones ----------------
     */

    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Padres / Tutores vinculados (usuarios con rol "padre").
     * Pivote: parent_student (user_id ↔ student_id)
     */
    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * ---------------- Accessors / Mutators ----------------
     */

    // Nombre completo cómodo
    public function getFullNameAttribute(): string
    {
        return trim(
            implode(' ', array_filter([
                $this->paternal_lastname,
                $this->maternal_lastname,
                $this->names,
            ]))
        );
    }

    // Normaliza el email en minúsculas al guardar
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? mb_strtolower(trim($value)) : null;
    }

    /**
     * ---------------- Scopes útiles ----------------
     */

    // Solo activos
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    // Por ciclo escolar
    public function scopeOfYear($query, $schoolYearId)
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    // Por grupo
    public function scopeOfGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function preceptors()
    {
        return $this->belongsToMany(User::class, 'preceptor_student', 'student_id', 'preceptor_id')->withTimestamps();
    }
}
