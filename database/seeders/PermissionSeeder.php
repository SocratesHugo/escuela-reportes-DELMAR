<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpia cache de Spatie antes de modificar
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $guard = 'web';

            // 1) Permisos (en español, agrupados por módulo)
            $permisos = [
                // Trabajos
                'trabajos.ver-lista',
                'trabajos.ver',
                'trabajos.crear',
                'trabajos.editar',
                'trabajos.eliminar',
                'trabajos.exportar',

                // Reportes / Resumen
                'reportes.ver-lista',
                'reportes.ver-alumno',
                'reportes.exportar',

                // Asignaciones (materia↔grupo↔maestro)
                'asignaciones.ver-lista',
                'asignaciones.gestionar',

                // Alumnos
                'alumnos.ver-lista',
                'alumnos.ver',
                'alumnos.editar',

                // Grupos
                'grupos.ver-lista',
                'grupos.gestionar',

                // Horarios
                'horarios.ver-lista',
                'horarios.gestionar',

                // Usuarios
                'usuarios.ver-lista',
                'usuarios.ver',
                'usuarios.crear',
                'usuarios.editar',
                'usuarios.eliminar',

                // Roles / Permisos
                'roles.ver-lista',
                'roles.ver',
                'roles.crear',
                'roles.editar',
                'roles.eliminar',

                // Panel / Auditoría
                'tablero.ver',
                'auditoria.ver',

                //Exportar alumnos masivamente
                'students.import',
            ];

            foreach ($permisos as $p) {
                Permission::findOrCreate($p, $guard);
            }

            // 2) Roles
            $admin       = Role::findOrCreate('admin', $guard);
            $director    = Role::findOrCreate('director', $guard);
            $coordinador = Role::findOrCreate('coordinador', $guard);
            $maestro     = Role::findOrCreate('maestro', $guard);
            $preceptor   = Role::findOrCreate('preceptor', $guard);
            $titular     = Role::findOrCreate('titular', $guard);

            // 3) Permisos por rol

            // Admin / Director: TODO lo que exista (incluye permisos nuevos que agregues en otros seeders)
            $allPermissionNames = Permission::pluck('name');
            $admin->syncPermissions($allPermissionNames);
            $director->syncPermissions($allPermissionNames);

            // Coordinador
            $coordinador->syncPermissions([
                'tablero.ver', 'auditoria.ver',
                'trabajos.ver-lista','trabajos.ver','trabajos.crear','trabajos.editar','trabajos.eliminar','trabajos.exportar',
                'reportes.ver-lista','reportes.ver-alumno','reportes.exportar',
                'asignaciones.ver-lista','asignaciones.gestionar',
                'alumnos.ver-lista','alumnos.ver','alumnos.editar',
                'grupos.ver-lista','grupos.gestionar',
                'horarios.ver-lista','horarios.gestionar',
                'usuarios.ver-lista','usuarios.ver','usuarios.editar',
                'roles.ver-lista','roles.ver',
            ]);

            // Maestro
            $maestro->syncPermissions([
                'tablero.ver',
                'trabajos.ver-lista','trabajos.ver','trabajos.crear','trabajos.editar','trabajos.eliminar',
            ]);

            // Preceptor
            $preceptor->syncPermissions([
                'tablero.ver',
                'reportes.ver-lista','reportes.ver-alumno',
                'alumnos.ver-lista','alumnos.ver',
            ]);

            // Titular
            $titular->syncPermissions([
                'tablero.ver',
                'reportes.ver-lista','reportes.ver-alumno',
                'alumnos.ver-lista','alumnos.ver',
            ]);

            // (Opcional) Asigna roles a usuarios de prueba
            // $this->assignIfExists('admin@example.com', 'admin');
            // $this->assignIfExists('coordinador@example.com', 'coordinador');
            // $this->assignIfExists('maestro@example.com', 'maestro');
            // $this->assignIfExists('preceptor@example.com', 'preceptor');
            // $this->assignIfExists('titular@example.com', 'titular');
        });

        // Limpia cache de Spatie al final
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // private function assignIfExists(string $email, string $role): void
    // {
    //     if ($u = \App\Models\User::where('email', $email)->first()) {
    //         $u->syncRoles([$role]);
    //     }
    // }
}
