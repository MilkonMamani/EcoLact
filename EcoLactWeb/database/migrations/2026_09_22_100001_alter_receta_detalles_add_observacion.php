<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota opcional por ingrediente ("Materia prima principal", "Coagulante"...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receta_detalles', function (Blueprint $table) {
            $table->string('observacion', 255)->nullable()->after('unidad');
        });
    }

    public function down(): void
    {
        Schema::table('receta_detalles', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
