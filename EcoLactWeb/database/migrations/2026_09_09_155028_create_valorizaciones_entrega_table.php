<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valorizaciones_entrega', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('entrega_id');
            $table->unsignedBigInteger('analisis_id');
            $table->unsignedBigInteger('precio_leche_id');
            $table->unsignedBigInteger('regla_pago_id');
            $table->decimal('litros_entregados', 10, 2);
            $table->decimal('litros_pagables', 10, 2);
            $table->decimal('precio_base_litro', 10, 4);
            $table->decimal('ajuste_por_litro', 10, 4);
            $table->decimal('precio_final_litro', 10, 4);
            $table->decimal('importe_total', 12, 2);
            $table->dateTime('fecha_calculo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('entrega_id', 'uq_valorizaciones_entrega_entrega_id');

            $table->foreign('entrega_id', 'fk_valorizaciones_entrega_entrega_id')
                ->references('id')->on('entregas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('analisis_id', 'fk_valorizaciones_entrega_analisis_id')
                ->references('id')->on('analisis_calidad')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('precio_leche_id', 'fk_valorizaciones_entrega_precio_leche_id')
                ->references('id')->on('precios_leche')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('regla_pago_id', 'fk_valorizaciones_entrega_regla_pago_id')
                ->references('id')->on('reglas_pago_calidad')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valorizaciones_entrega');
    }
};
