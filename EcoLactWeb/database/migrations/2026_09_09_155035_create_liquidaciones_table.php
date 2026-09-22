<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('periodo_id');
            $table->unsignedBigInteger('proveedor_id');
            $table->decimal('total_litros_entregados', 12, 2);
            $table->decimal('total_litros_pagables', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_ajustes', 12, 2);
            $table->decimal('total_pagar', 12, 2);
            $table->string('estado', 20);
            $table->dateTime('fecha_calculo');
            $table->unsignedBigInteger('generado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['periodo_id', 'proveedor_id'], 'uq_liquidaciones_periodo_id_proveedor_id');
            $table->index(['proveedor_id', 'estado'], 'idx_liquidaciones_proveedor_id_estado');

            $table->foreign('periodo_id', 'fk_liquidaciones_periodo_id')
                ->references('id')->on('periodos_liquidacion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('proveedor_id', 'fk_liquidaciones_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('generado_por_user_id', 'fk_liquidaciones_generado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
