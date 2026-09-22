<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `insumo_movimientos` es la ÚNICA fuente de verdad del stock de insumos
 * (`insumos.stock_actual` es solo un saldo cacheado que se actualiza en la
 * misma transacción de cada movimiento).
 *
 * `tipo` sigue siendo la dirección (ENTRADA/SALIDA). `motivo` pasa a ser el
 * catálogo cerrado INGRESO_ACOPIO, INGRESO_COMPRA, CONSUMO_PRODUCCION,
 * AJUSTE_ENTRADA, AJUSTE_SALIDA, REVERSO u OTRO, y `origen_tipo`/`origen_id`
 * dejan trazado qué registro lo originó (ENTREGA, LOTE_PRODUCCION...).
 *
 * Los motivos históricos se conservan: COMPRA -> INGRESO_COMPRA,
 * AJUSTE -> AJUSTE_ENTRADA/AJUSTE_SALIDA según la dirección, y cualquier
 * otro texto libre pasa a OTRO copiando el motivo original a la observación.
 */
return new class extends Migration
{
    private const MOTIVOS = [
        'INGRESO_ACOPIO', 'INGRESO_COMPRA', 'CONSUMO_PRODUCCION',
        'AJUSTE_ENTRADA', 'AJUSTE_SALIDA', 'REVERSO', 'OTRO',
    ];

    public function up(): void
    {
        Schema::table('insumo_movimientos', function (Blueprint $table) {
            $table->string('origen_tipo', 30)->nullable()->after('lote_produccion_id');
            $table->unsignedBigInteger('origen_id')->nullable()->after('origen_tipo');
            $table->index(['origen_tipo', 'origen_id'], 'idx_insumo_movimientos_origen');
        });

        Schema::table('insumo_movimientos', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 4)->change();
        });

        foreach (DB::table('insumo_movimientos')->get() as $movimiento) {
            $motivo = (string) $movimiento->motivo;
            $observacion = $movimiento->observacion;

            if ($motivo === 'COMPRA') {
                $nuevo = 'INGRESO_COMPRA';
            } elseif ($motivo === 'AJUSTE') {
                $nuevo = $movimiento->tipo === 'ENTRADA' ? 'AJUSTE_ENTRADA' : 'AJUSTE_SALIDA';
            } elseif (in_array($motivo, self::MOTIVOS, true)) {
                $nuevo = $motivo;
            } else {
                $nuevo = 'OTRO';
                $observacion = trim($motivo.($observacion !== null && $observacion !== '' ? ' — '.$observacion : ''));
            }

            DB::table('insumo_movimientos')->where('id', $movimiento->id)->update([
                'motivo' => $nuevo,
                'observacion' => $observacion,
                'origen_tipo' => $movimiento->lote_produccion_id !== null ? 'LOTE_PRODUCCION' : null,
                'origen_id' => $movimiento->lote_produccion_id,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('insumo_movimientos')->where('motivo', 'INGRESO_COMPRA')->update(['motivo' => 'COMPRA']);
        DB::table('insumo_movimientos')->whereIn('motivo', ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA'])->update(['motivo' => 'AJUSTE']);

        Schema::table('insumo_movimientos', function (Blueprint $table) {
            $table->dropIndex('idx_insumo_movimientos_origen');
            $table->dropColumn(['origen_tipo', 'origen_id']);
        });

        Schema::table('insumo_movimientos', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 2)->change();
        });
    }
};
