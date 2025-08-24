<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Ejecuta la inserción de roles iniciales en la base de datos.
     * Usamos firstOrCreate para evitar duplicados si se corre más de una vez.
     */
    public function run(): void
    {
        $roles = [
            'admin',            // Acceso total al sistema
            'director',         // Dirección general
            'coordinador',      // Coordinador académico
            'director_seccion', // Dirección por sección
            'titular',          // Titular de grupo
            'maestro',          // Profesor de materias
            'preceptor',        // Encargado de seguimiento de alumnos
            'padre',            // Padre/madre/tutor
            'alumno',           // Alumno del sistema
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}

