<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->decimal('precio_litro_aplicado', 10, 4)->after('total_litros_pagables');
            $table->boolean('tuvo_incidencia')->default(false)->after('precio_litro_aplicado');
            $table->unsignedBigInteger('sancion_id')->nullable()->after('tuvo_incidencia');

            $table->foreign('sancion_id', 'fk_liquidaciones_sancion_id')
                ->references('id')->on('sanciones')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liquidaciones', function (Blueprint $table) {
            $table->dropForeign('fk_liquidaciones_sancion_id');
            $table->dropColumn(['precio_litro_aplicado', 'tuvo_incidencia', 'sancion_id']);
        });
    }
};
