<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría del pago semanal a proveedores ("Imprimir y marcar pagados"):
 *  - pagado_at: fecha y hora exactas en que se confirmó la entrega del efectivo
 *    (fecha_pago solo guarda el día).
 *  - impreso_at: momento en que el administrador confirmó la impresión.
 *  - lote_codigo: agrupa los pagos confirmados en la misma acción de impresión.
 *  - comprobante_snapshot: copia inmutable de lo que se imprimió (datos del
 *    proveedor, cálculo y productos descontados), de modo que el comprobante de un
 *    pago ya realizado no cambie si luego se edita el proveedor, la zona o un producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dateTime('pagado_at')->nullable()->after('fecha_pago');
            $table->dateTime('impreso_at')->nullable()->after('pagado_at');
            $table->string('lote_codigo', 40)->nullable()->after('impreso_at');
            $table->json('comprobante_snapshot')->nullable()->after('lote_codigo');

            $table->index('lote_codigo', 'idx_pagos_lote_codigo');
        });

        DB::table('pagos')->whereNull('pagado_at')->update(['pagado_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex('idx_pagos_lote_codigo');
            $table->dropColumn(['pagado_at', 'impreso_at', 'lote_codigo', 'comprobante_snapshot']);
        });
    }
};
