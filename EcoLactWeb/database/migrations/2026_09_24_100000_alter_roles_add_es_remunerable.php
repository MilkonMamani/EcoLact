<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indica qué roles cobran un sueldo. Configuración de pagos y la generación de
 * sueldos leen esta bandera, de modo que un rol nuevo marcado como
 * remunerable aparece solo, sin tocar código. PROVEEDOR no es personal: cobra
 * por la leche entregada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('es_remunerable')->default(false)->after('descripcion');
        });

        DB::table('roles')
            ->whereIn('nombre', ['ADMINISTRADOR', 'ACOPIADOR', 'ENCARGADO_PLANTA', 'VENTAS', 'CALIDAD'])
            ->update(['es_remunerable' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('es_remunerable');
        });
    }
};
