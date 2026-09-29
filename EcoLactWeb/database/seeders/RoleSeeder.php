<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'ADMINISTRADOR',
            'ACOPIADOR',
            'PROVEEDOR',
            'ENCARGADO_PLANTA',
            'VENTAS',
            'CALIDAD',
        ];

        foreach ($roles as $nombre) {
            DB::table('roles')->updateOrInsert(
                ['nombre' => $nombre],
                ['es_remunerable' => $nombre !== 'PROVEEDOR', 'controla_asistencia' => $nombre !== 'ADMINISTRADOR', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
