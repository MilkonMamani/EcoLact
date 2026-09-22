<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = [
            ['nombre' => 'Queso Paria Fresco', 'unidad_medida' => 'UNIDAD'],
            ['nombre' => 'Queso Paria Pasteurizado', 'unidad_medida' => 'UNIDAD'],
            ['nombre' => 'Queso Madurado', 'unidad_medida' => 'UNIDAD'],
            ['nombre' => 'Yogur', 'unidad_medida' => 'LITRO'],
        ];

        foreach ($productos as $producto) {
            DB::table('productos')->updateOrInsert(
                ['nombre' => $producto['nombre']],
                [
                    'unidad_medida' => $producto['unidad_medida'],
                    'estado' => 'ACTIVO',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
