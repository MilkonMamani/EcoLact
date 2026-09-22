<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = DB::table('roles')->where('nombre', 'ADMINISTRADOR')->first();

        if (! $role) {
            $this->command?->error('El rol ADMINISTRADOR no existe. Ejecuta RoleSeeder antes de AdminUserSeeder.');

            return;
        }

        $username = env('ECOLACT_ADMIN_USERNAME', 'admin');
        $password = env('ECOLACT_ADMIN_PASSWORD', 'admin');

        DB::table('users')->updateOrInsert(
            ['username' => $username],
            [
                'role_id' => $role->id,
                'password' => Hash::make($password),
                'estado' => 'ACTIVO',
                'debe_cambiar_password' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
