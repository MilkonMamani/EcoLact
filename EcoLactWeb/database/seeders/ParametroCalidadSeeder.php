<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParametroCalidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parametros = [
            ['codigo' => 'DENSIDAD', 'nombre' => 'Densidad', 'unidad' => 'g/mL'],
            ['codigo' => 'GRASA', 'nombre' => 'Grasa', 'unidad' => '%'],
            ['codigo' => 'PROTEINA', 'nombre' => 'Proteína', 'unidad' => '%'],
            ['codigo' => 'LACTOSA', 'nombre' => 'Lactosa', 'unidad' => '%'],
            ['codigo' => 'PH', 'nombre' => 'pH', 'unidad' => 'pH'],
            ['codigo' => 'AGUA_ANADIDA', 'nombre' => 'Agua añadida', 'unidad' => '%'],
            ['codigo' => 'TEMPERATURA', 'nombre' => 'Temperatura', 'unidad' => '°C'],
        ];

        foreach ($parametros as $parametro) {
            DB::table('parametros_calidad')->updateOrInsert(
                ['codigo' => $parametro['codigo']],
                [
                    'nombre' => $parametro['nombre'],
                    'unidad' => $parametro['unidad'],
                    'estado' => 'ACTIVO',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
