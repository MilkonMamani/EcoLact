<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZonaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonas = [
            'Faón',
            'Collana 1',
            'Collana 2',
            'Kapi',

        ];

        foreach ($zonas as $nombre) {
            DB::table('zonas')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'estado' => 'ACTIVO',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
