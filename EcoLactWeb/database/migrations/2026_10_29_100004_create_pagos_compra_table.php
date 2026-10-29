<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada desembolso REAL hecho por una compra (contado, cuota, saldo final).
 * Una compra de S/ 1,000 pagada 400 + 600 tiene dos filas, y cada fila
 * genera exactamente un movimiento de Caja (PAGO_COMPRA #id) cuyo id se
 * guarda en `movimiento_caja_id` (único: un movimiento, un pago).
 *
 * `metodo_pago` es nullable solo por las compras históricas: la salida de
 * caja de C-0001 se registró sin método.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_compra', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('compra_insumo_id');
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago', 20)->nullable();
            $table->string('numero_operacion', 60)->nullable();
            $table->dateTime('fecha');
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('movimiento_caja_id')->nullable()->unique('uq_pagos_compra_movimiento_caja_id');
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamps();

            $table->index(['compra_insumo_id', 'fecha'], 'idx_pagos_compra_compra_fecha');

            $table->foreign('compra_insumo_id', 'fk_pagos_compra_compra_insumo_id')
                ->references('id')->on('compras_insumo')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('movimiento_caja_id', 'fk_pagos_compra_movimiento_caja_id')
                ->references('id')->on('movimientos_caja')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('registrado_por_user_id', 'fk_pagos_compra_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_compra');
    }
};
