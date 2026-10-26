<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descripción corta de QUÉ mide el parámetro (p. ej. "Mide la densidad de la
 * leche."). Solo informativa: no participa en la evaluación de reglas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametros_calidad', function (Blueprint $table) {
            $table->string('descripcion', 200)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('parametros_calidad', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
