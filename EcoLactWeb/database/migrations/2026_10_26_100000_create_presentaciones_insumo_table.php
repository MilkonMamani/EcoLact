<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentaciones comerciales en que se COMPRA un insumo (Saco 25 kg, Balde
 * 20 L...). `cantidad` + `unidad` es lo que contiene una presentación y
 * `cantidad_base` su equivalencia en la unidad base del insumo, calculada con
 * UnidadMedida al guardarla. La boleta solo dice "2 SACOS SAL": cuánto trae un
 * saco lo sabe EcoLact por esta tabla. La leche nunca tiene presentaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentaciones_insumo', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('insumo_id');
            $table->string('nombre', 60);
            $table->decimal('cantidad', 12, 4);
            $table->string('unidad', 10);
            $table->decimal('cantidad_base', 14, 4);
            $table->string('estado', 10)->default('ACTIVA');
            $table->timestamps();

            $table->index(['insumo_id', 'estado'], 'idx_presentaciones_insumo_insumo_estado');

            $table->foreign('insumo_id', 'fk_presentaciones_insumo_insumo_id')
                ->references('id')->on('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentaciones_insumo');
    }
};
