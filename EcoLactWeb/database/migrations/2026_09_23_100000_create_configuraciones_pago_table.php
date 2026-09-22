<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regla vigente que define CUÁNTO y CÓMO se paga: a proveedores (por litros,
 * con el precio vigente de precios_leche; aquí NO se guarda precio) o al
 * personal (sueldo fijo mensual por rol). Es versionada: cambiar un sueldo
 * cierra la vigencia anterior (fecha_fin) y abre una nueva, nunca reescribe
 * el pasado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_pago', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('tipo_beneficiario', 20);
            $table->unsignedBigInteger('rol_id')->nullable();
            $table->string('tipo_calculo', 30);
            $table->decimal('monto_base', 12, 2)->nullable();
            $table->string('periodicidad', 20);
            $table->boolean('aplica_calidad')->default(false);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('creado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['tipo_beneficiario', 'rol_id', 'estado', 'fecha_inicio'], 'idx_configuraciones_pago_clave_vigencia');

            $table->foreign('rol_id', 'fk_configuraciones_pago_rol_id')
                ->references('id')->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('creado_por_user_id', 'fk_configuraciones_pago_creado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_pago');
    }
};
