<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un día trabajado de una persona del personal remunerable (ACOPIADOR,
 * CALIDAD, ENCARGADO_PLANTA, VENTAS, ADMINISTRADOR). Se identifica por
 * user_id + rol_id, igual que obligaciones_pago, nunca por una tabla de
 * ficha (que no todos los roles tienen). No se guardan filas "no
 * trabajado": la ausencia de fila en una fecha ES "no trabajado".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jornadas_personal', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('rol_id');
            $table->date('fecha');
            $table->string('estado', 20)->default('TRABAJADO');
            $table->string('observacion', 500)->nullable();
            $table->unsignedBigInteger('registrado_por_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'fecha'], 'uq_jornadas_personal_user_fecha');

            $table->foreign('user_id', 'fk_jornadas_personal_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('rol_id', 'fk_jornadas_personal_rol_id')
                ->references('id')->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('registrado_por_user_id', 'fk_jornadas_personal_registrado_por')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornadas_personal');
    }
};
