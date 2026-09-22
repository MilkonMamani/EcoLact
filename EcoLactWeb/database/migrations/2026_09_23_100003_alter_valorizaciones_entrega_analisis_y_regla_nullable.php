<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La calidad no se analiza en TODAS las entregas. Una entrega sin análisis
 * se valoriza al precio base sin ajuste, así que su valorización no tiene
 * análisis ni regla de pago asociados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valorizaciones_entrega', function (Blueprint $table) {
            $table->unsignedBigInteger('analisis_id')->nullable()->change();
            $table->unsignedBigInteger('regla_pago_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('valorizaciones_entrega', function (Blueprint $table) {
            $table->unsignedBigInteger('analisis_id')->nullable(false)->change();
            $table->unsignedBigInteger('regla_pago_id')->nullable(false)->change();
        });
    }
};
