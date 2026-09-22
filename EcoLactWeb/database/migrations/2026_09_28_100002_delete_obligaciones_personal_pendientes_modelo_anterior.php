<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corte de modelo: las obligaciones de personal PENDIENTES generadas bajo el
 * esquema anterior (mes calendario, sueldo completo desde el día 1, sin
 * jornadas) ya no son válidas bajo las reglas nuevas (ventanas móviles de 30
 * días, pago solo tras 30 jornadas registradas), así que nunca serían
 * reutilizadas de todos modos. Se eliminan aquí, una sola vez; el generador
 * corregido (GenerarObligacionesPersonal) crea sus reemplazos EN_CURSO
 * (0/30, sin historial fabricado) la próxima vez que se visite "Pagar a
 * trabajadores". Las obligaciones PAGADAS (de cualquier tipo) nunca se
 * tocan: son historial financiero.
 *
 * No hay down(): es un corte de datos intencional, no reversible (igual que
 * 2026_09_23_100004_backfill_obligaciones_desde_liquidaciones.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('obligaciones_pago')
            ->where('tipo_beneficiario', 'PERSONAL')
            ->where('estado', 'PENDIENTE')
            ->delete();
    }

    public function down(): void
    {
        // Intencional: no se puede reconstruir el estado anterior sin fabricar historial falso.
    }
};
