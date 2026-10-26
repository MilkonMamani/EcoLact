<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de una compra de insumos. Guarda una FOTOGRAFÍA de la presentación
 * usada (nombre, contenido y equivalencia): si mañana el saco pasa de 25 a 50
 * kg, la compra histórica sigue diciendo 2 × Saco 25 kg = 50 000 g.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_insumo_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('compra_insumo_id');
            $table->unsignedBigInteger('insumo_id');
            $table->unsignedBigInteger('presentacion_insumo_id')->nullable();
            $table->string('presentacion_nombre', 60);
            $table->decimal('presentacion_cantidad', 12, 4);
            $table->string('presentacion_unidad', 10);
            $table->decimal('cantidad_presentaciones', 12, 2);
            $table->decimal('cantidad_base', 14, 4);
            $table->string('unidad_base', 10);
            $table->unsignedBigInteger('insumo_movimiento_id')->nullable();
            $table->timestamps();

            $table->foreign('compra_insumo_id', 'fk_compra_insumo_detalles_compra_id')
                ->references('id')->on('compras_insumo')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('insumo_id', 'fk_compra_insumo_detalles_insumo_id')
                ->references('id')->on('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('presentacion_insumo_id', 'fk_compra_insumo_detalles_presentacion_id')
                ->references('id')->on('presentaciones_insumo')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('insumo_movimiento_id', 'fk_compra_insumo_detalles_movimiento_id')
                ->references('id')->on('insumo_movimientos')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_insumo_detalles');
    }
};
