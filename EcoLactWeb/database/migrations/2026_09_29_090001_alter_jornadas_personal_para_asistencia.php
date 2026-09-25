<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 1 de 2 (ver también 2026_09_29_090004): agrega el enlace a
 * `trabajadores` (todavía nullable: el backfill ocurre en
 * 2026_09_29_090003, que necesita que `trabajadores` ya tenga filas),
 * `origen` y `hora_marcacion`, y redefine la columna `estado` que ya
 * existía (creada en 2026_09_28_100000 con el valor implícito
 * "TRABAJADO", sin distinguir FALTA) para los valores PRESENTE/FALTA del
 * modelo de asistencia real. `user_id` y el índice único (user_id, fecha) se
 * quedan tal cual hasta que el backfill termine; 2026_09_29_090004 los
 * reemplaza por trabajador_id NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jornadas_personal', function (Blueprint $table) {
            $table->unsignedBigInteger('trabajador_id')->nullable()->after('id');
            $table->string('origen', 20)->default('MANUAL')->after('estado');
            $table->dateTime('hora_marcacion')->nullable()->after('origen');

            $table->foreign('trabajador_id', 'fk_jornadas_personal_trabajador_id')
                ->references('id')->on('trabajadores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('estado', 'idx_jornadas_personal_estado');
        });

        DB::table('jornadas_personal')->where('estado', 'TRABAJADO')->update(['estado' => 'PRESENTE']);

        Schema::table('jornadas_personal', function (Blueprint $table) {
            $table->string('estado', 10)->default('PRESENTE')->change();
        });
    }

    public function down(): void
    {
        Schema::table('jornadas_personal', function (Blueprint $table) {
            $table->dropForeign('fk_jornadas_personal_trabajador_id');
            $table->dropIndex('idx_jornadas_personal_estado');
            $table->dropColumn(['trabajador_id', 'origen', 'hora_marcacion']);
            $table->string('estado', 20)->default('TRABAJADO')->change();
        });
    }
};
