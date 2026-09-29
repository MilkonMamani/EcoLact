<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indica qué roles remunerables marcan asistencia. El ADMINISTRADOR cobra
 * sueldo fijo mensual y nadie por encima de él controla su horario: no
 * marca asistencia, no recibe faltas automáticas y no se le descuenta por
 * faltas. Igual que es_remunerable, un rol nuevo solo necesita esta bandera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('controla_asistencia')->default(true)->after('es_remunerable');
        });

        DB::table('roles')->where('nombre', 'ADMINISTRADOR')->update(['controla_asistencia' => false]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('controla_asistencia');
        });
    }
};
