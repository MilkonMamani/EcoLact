<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja: el dinero real que entra (ventas al contado) y sale (pagos a
 * proveedores y trabajadores, compras de insumos) de EcoLact. Nadie escribe
 * aquí a mano: cada fila nace en la misma transacción que la operación que la
 * origina y la identifica `referencia_tipo` + `referencia_id`, que es ÚNICO
 * (un evento de negocio genera como máximo un movimiento).
 *
 * `monto` es siempre positivo; el signo lo da `tipo` (ENTRADA/SALIDA). El
 * saldo no se guarda: es SUMA(entradas) − SUMA(salidas).
 *
 * `concepto`, `descripcion`, `referencia` y `detalle` son una fotografía de la
 * operación al confirmarse (la caja es un historial: no cambia si después se
 * renombra un comprador o un proveedor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('tipo', 10);
            $table->string('categoria', 15);
            $table->decimal('monto', 12, 2);
            $table->string('concepto', 120);
            $table->string('descripcion', 150)->nullable();
            $table->string('referencia', 60);
            $table->json('detalle')->nullable();
            $table->string('referencia_tipo', 30);
            $table->unsignedBigInteger('referencia_id');
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->dateTime('fecha');
            $table->timestamps();

            $table->unique(['referencia_tipo', 'referencia_id'], 'uq_movimientos_caja_referencia');
            $table->index('fecha', 'idx_movimientos_caja_fecha');
            $table->index(['categoria', 'fecha'], 'idx_movimientos_caja_categoria_fecha');

            $table->foreign('registrado_por_user_id', 'fk_movimientos_caja_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
