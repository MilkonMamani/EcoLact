<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReglaPagoCalidadSeeder extends Seeder
{
    public function run(): void
    {
        $reglas = [
            ['resultado_calidad' => 'ACEPTADA', 'valor_ajuste' => 0.00],
            ['resultado_calidad' => 'OBSERVADA', 'valor_ajuste' => -0.10],
            ['resultado_calidad' => 'RECHAZADA', 'valor_ajuste' => 0.00],
        ];

        foreach ($reglas as $regla) {
            DB::table('reglas_pago_calidad')->updateOrInsert(
                [
                    'resultado_calidad' => $regla['resultado_calidad'],
                    'fecha_inicio' => '2020-01-01',
                    'fecha_fin' => null,
                ],
                [
                    'tipo_ajuste' => 'MONTO',
                    'valor_ajuste' => $regla['valor_ajuste'],
                    'estado' => 'ACTIVO',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
