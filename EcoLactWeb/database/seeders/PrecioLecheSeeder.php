<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrecioLecheSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')
            ->where('username', env('ECOLACT_ADMIN_USERNAME', 'admin'))
            ->value('id');

        if ($adminId === null) {
            return;
        }

        DB::table('precios_leche')->updateOrInsert(
            ['fecha_inicio' => '2020-01-01', 'fecha_fin' => null],
            [
                'precio_litro' => 1.70,
                'estado' => 'ACTIVO',
                'creado_por_user_id' => $adminId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
