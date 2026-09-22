<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago REAL: el dinero ya fue entregado. Es un registro financiero: una
 * obligación tiene a lo sumo un pago (unique) y por el monto exacto
 * calculado (sin pagos parciales). El comprobante vive en Storage; aquí solo
 * se guarda su ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('obligacion_pago_id');
            $table->date('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago', 20);
            $table->string('numero_operacion', 100)->nullable();
            $table->text('observacion')->nullable();
            $table->string('comprobante_path', 255)->nullable();
            $table->string('comprobante_nombre', 255)->nullable();
            $table->unsignedBigInteger('pago_proveedor_id')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('obligacion_pago_id', 'uq_pagos_obligacion_pago_id');

            $table->foreign('obligacion_pago_id', 'fk_pagos_obligacion_pago_id')
                ->references('id')->on('obligaciones_pago')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('pago_proveedor_id', 'fk_pagos_pago_proveedor_id')
                ->references('id')->on('pagos_proveedor')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('registrado_por_user_id', 'fk_pagos_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
