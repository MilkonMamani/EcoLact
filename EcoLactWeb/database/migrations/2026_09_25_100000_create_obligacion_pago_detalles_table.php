<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entregas que componen un pago pendiente de proveedor, con el cálculo de cada
 * una (litros, precio base, ajuste de calidad, precio final y subtotal). Una
 * entrega pertenece a lo sumo a UN pago (unique): las entregas nuevas nunca
 * se suman a un pago ya PAGADO, generan otro pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligacion_pago_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('obligacion_pago_id');
            $table->unsignedBigInteger('entrega_id');
            $table->decimal('litros_entregados', 10, 2);
            $table->decimal('litros_pagables', 10, 2);
            $table->string('resultado_calidad', 20)->nullable();
            $table->decimal('precio_base', 10, 4);
            $table->decimal('ajuste_calidad', 10, 4)->default(0);
            $table->decimal('precio_final', 10, 4);
            $table->decimal('subtotal', 12, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('entrega_id', 'uq_obligacion_pago_detalles_entrega_id');
            $table->index('obligacion_pago_id', 'idx_obligacion_pago_detalles_obligacion_pago_id');

            $table->foreign('obligacion_pago_id', 'fk_obligacion_pago_detalles_obligacion_pago_id')
                ->references('id')->on('obligaciones_pago')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('entrega_id', 'fk_obligacion_pago_detalles_entrega_id')
                ->references('id')->on('entregas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligacion_pago_detalles');
    }
};
