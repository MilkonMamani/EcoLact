<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad laboral común, DERIVADA/SINCRONIZADA (no reemplaza las fichas por
 * rol: acopiadores, analistas_calidad, encargados_planta, personal_ventas
 * siguen existiendo con sus propios datos, como zona_id en acopiadores).
 * `trabajadores` proyecta a quien realmente es "personal pagable" (mismo
 * criterio que EloquentPersonalPagableQuery, que ya evita usuarios huérfanos
 * como cuentas con role_id de ACOPIADOR pero sin ficha real) y le da una
 * identidad estable para asistencia, QR y nómina, sin duplicar role_id (se
 * deriva vía user_id -> users.role_id -> roles). La sincroniza
 * SincronizarTrabajadores; no se edita a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('dni', 8)->nullable();
            $table->string('nombres', 100);
            $table->string('apellidos', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('estado', 20);
            $table->date('fecha_ingreso');
            $table->date('fecha_salida')->nullable();
            $table->string('qr_token', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('user_id', 'uq_trabajadores_user_id');
            $table->unique('qr_token', 'uq_trabajadores_qr_token');
            $table->index('estado', 'idx_trabajadores_estado');

            $table->foreign('user_id', 'fk_trabajadores_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};
