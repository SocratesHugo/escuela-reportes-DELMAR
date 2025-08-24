<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    use HasFactory, Notifiable;

    /** Spatie guard */
    protected $guard_name = 'web';

    /**
     * Campos masivos.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Ocultos.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Laravel 10+
    ];

    /**
     * Control de acceso al Panel de Filament.
     *
     * -------------------------
     * Opción A (simple, 1 panel):
     *  - Permitir también 'alumno' y 'padre' para que puedan ver su boleta.
     * -------------------------
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            'admin',
            'director',
            'coordinador',
            'maestro',
            'titular',
            'preceptor',
            // 👇 habilita acceso al mismo panel para alumnos y padres
            'alumno',
            'padre',
        ]);
    }

    /*
     * -------------------------
     * Opción B (2 paneles, opcional):
     * Si tu proyecto tiene dos paneles configurados (p. ej. 'admin' y 'parents'),
     * puedes usar esta versión en lugar de la de arriba:
     *
     * public function canAccessPanel(Panel $panel): bool
     * {
     *     if ($panel->getId() === 'admin') {
     *         return $this->hasAnyRole(['admin','director','coordinador','maestro','titular','preceptor']);
     *     }
     *     if ($panel->getId() === 'parents') {
     *         return $this->hasAnyRole(['alumno','padre']);
     *     }
     *     return false;
     * }
     *
     * -------------------------
     */

    /**
     * -------- Relaciones útiles --------
     */

    /** Padres → Hijos (cuando el usuario es "padre") */
    public function children()
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'user_id', 'student_id')
            ->withTimestamps();
    }

    /** Asignaciones del maestro (materia↔grupo) */
    public function assignments()
    {
        return $this->hasMany(GroupSubjectTeacher::class, 'teacher_id');
    }

    /** Estudiantes a su cargo como preceptor */
    public function preceptoredStudents()
    {
        return $this->belongsToMany(Student::class, 'preceptor_student', 'preceptor_id', 'student_id')
            ->withTimestamps();
    }

    /**
     * -------- Helpers de roles --------
     */

    public function isRole(string $role): bool
    {
        return $this->hasRole($role);
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('maestro');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /** Scope: solo maestros */
    public function scopeTeachers($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', 'maestro'));
    }

    // ...
public function homeroomsAsTeacher()
{
    return $this->hasMany(\App\Models\Homeroom::class, 'teacher_id');
}
}
