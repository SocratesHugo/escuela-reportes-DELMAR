<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea o actualiza el usuario administrador
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        // updateOrCreate actualiza si existe, crea si no existe
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'password' => Hash::make($password),
            ]
        );

        // Asigna el rol de admin
        $user->syncRoles(['admin']);
    }
}
