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
            ['codigo' => 'DENSIDAD', 'nombre' => 'Densidad', 'unidad' => 'g/mL', 'descripcion' => 'Mide la densidad de la leche.'],
            ['codigo' => 'GRASA', 'nombre' => 'Grasa', 'unidad' => '%', 'descripcion' => 'Contenido de grasa de la leche.'],
            ['codigo' => 'PROTEINA', 'nombre' => 'Proteína', 'unidad' => '%', 'descripcion' => 'Contenido de proteína de la leche.'],
            ['codigo' => 'LACTOSA', 'nombre' => 'Lactosa', 'unidad' => '%', 'descripcion' => 'Contenido de lactosa de la leche.'],
            ['codigo' => 'PH', 'nombre' => 'pH', 'unidad' => 'pH', 'descripcion' => 'Mide la acidez o alcalinidad de la leche.'],
            ['codigo' => 'AGUA_ANADIDA', 'nombre' => 'Agua añadida', 'unidad' => '%', 'descripcion' => 'Porcentaje de agua agregada a la leche.'],
            ['codigo' => 'TEMPERATURA', 'nombre' => 'Temperatura', 'unidad' => '°C', 'descripcion' => 'Temperatura de la leche al recibirla.'],
        ];

        foreach ($parametros as $parametro) {
            DB::table('parametros_calidad')->updateOrInsert(
                ['codigo' => $parametro['codigo']],
                [
                    'nombre' => $parametro['nombre'],
                    'unidad' => $parametro['unidad'],
                    'descripcion' => $parametro['descripcion'],
                    'estado' => 'ACTIVO',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
