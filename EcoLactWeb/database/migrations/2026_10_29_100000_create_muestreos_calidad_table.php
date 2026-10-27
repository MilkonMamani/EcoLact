<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Muestreo de calidad: Calidad no analiza todas las entregas, solo las que
 * selecciona (al azar, manualmente o por muestreo directo en campo). Es una
 * tabla aparte de entregas para no mezclarlo con EstadoEntrega.
 *
 * `entrega_id` es único: una entrega se selecciona una sola vez, aunque dos
 * analistas lo intenten a la vez (el segundo recibe un conflicto).
 * `fecha_seleccion` / `fecha_captura_local` guardan la hora real del evento en
 * el teléfono; created_at es la hora en que llegó al servidor (sincronización).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muestreos_calidad', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('entrega_id')->unique('uq_muestreos_calidad_entrega');
            $table->unsignedBigInteger('seleccionado_por_user_id');
            $table->string('metodo_seleccion', 12);
            $table->string('tipo_control', 12)->nullable();
            $table->dateTime('fecha_seleccion');
            $table->dateTime('fecha_captura_local')->nullable();
            $table->string('estado', 12)->default('SELECCIONADA');
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_seleccion'], 'idx_muestreos_calidad_estado');

            $table->foreign('entrega_id')->references('id')->on('entregas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('seleccionado_por_user_id')->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muestreos_calidad');
    }
};
