<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago PENDIENTE (o ya PAGADO) de un beneficiario en un periodo: cuánto se
 * debe. Guarda un snapshot inmutable del cálculo (monto_calculado y
 * base_calculo), de modo que cambiar después la configuración no altera lo
 * ya generado. `clave_unicidad` garantiza que generar dos veces el mismo
 * beneficiario/periodo no duplique la obligación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligaciones_pago', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('tipo_beneficiario', 20);
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('rol_id')->nullable();
            $table->string('beneficiario_nombre', 255);
            $table->unsignedBigInteger('configuracion_pago_id')->nullable();
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->string('periodicidad', 20);
            $table->string('concepto', 150);
            $table->json('base_calculo')->nullable();
            $table->decimal('monto_calculado', 12, 2);
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('clave_unicidad', 120);
            $table->unsignedBigInteger('creado_por_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('clave_unicidad', 'uq_obligaciones_pago_clave_unicidad');
            $table->index(['estado', 'periodo_inicio'], 'idx_obligaciones_pago_estado_periodo');
            $table->index(['tipo_beneficiario', 'periodo_inicio'], 'idx_obligaciones_pago_tipo_periodo');

            $table->foreign('proveedor_id', 'fk_obligaciones_pago_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('user_id', 'fk_obligaciones_pago_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('rol_id', 'fk_obligaciones_pago_rol_id')
                ->references('id')->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('configuracion_pago_id', 'fk_obligaciones_pago_configuracion_pago_id')
                ->references('id')->on('configuraciones_pago')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('liquidacion_id', 'fk_obligaciones_pago_liquidacion_id')
                ->references('id')->on('liquidaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('creado_por_user_id', 'fk_obligaciones_pago_creado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligaciones_pago');
    }
};
