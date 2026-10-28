<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 2 de 2 (ver 2026_09_29_090001): con trabajador_id ya poblado por
 * 2026_09_29_090003, lo vuelve obligatorio y reemplaza `user_id` (redundante:
 * trabajadores.user_id ya es único) como identificador de la jornada.
 * `rol_id` se conserva como snapshot histórico, igual patrón que
 * obligaciones_pago.rol_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jornadas_personal', function (Blueprint $table) {
            // SQLite (tests) no borra claves foráneas por nombre; MySQL usa el nombre real.
            $table->dropForeign(
                Schema::getConnection()->getDriverName() === 'sqlite'
                    ? ['user_id']
                    : 'fk_jornadas_personal_user_id'
            );
            $table->dropUnique('uq_jornadas_personal_user_fecha');
            $table->dropColumn('user_id');
            $table->unsignedBigInteger('trabajador_id')->nullable(false)->change();
            $table->unique(['trabajador_id', 'fecha'], 'uq_jornadas_personal_trabajador_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('jornadas_personal', function (Blueprint $table) {
            $table->dropUnique('uq_jornadas_personal_trabajador_fecha');
            $table->unsignedBigInteger('trabajador_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        Schema::table('jornadas_personal', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_jornadas_personal_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
