<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha en la que un pendiente de personal queda habilitado para pagarse:
 * siempre el día 1 del mes siguiente al periodo trabajado (regla fija del
 * negocio). Se fija al cerrar el mes (CerrarNominaMensual); en proveedor
 * queda nula y se ignora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->date('fecha_pago_programada')->nullable()->after('periodo_fin');
        });
    }

    public function down(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->dropColumn('fecha_pago_programada');
        });
    }
};
