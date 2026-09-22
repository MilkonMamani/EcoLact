<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ruta_detalles guarda EXCLUSIVAMENTE la asignación vigente de cada
     * proveedor (equivalente a "carrito" de la ruta). Cuando un proveedor se
     * traslada o se retira, su fila se borra de aquí y el movimiento queda
     * en proveedor_ruta_historial (mismo patrón que proveedores.zona_id /
     * proveedor_zona_historial). Como nunca conviven filas vigentes e
     * históricas en esta tabla, las restricciones únicas pueden ser simples:
     *
     * - uq_ruta_detalles_proveedor_id: un proveedor tiene como máximo una
     *   asignación vigente entre TODAS las rutas.
     * - uq_ruta_detalles_ruta_id_orden_visita: dentro de una misma ruta, el
     *   orden de visita no se repite entre los proveedores vigentes.
     *
     * orden_visita es un entero CON signo (no unsignedInteger): la
     * reordenación transaccional (ReordenarRutaDetalles) necesita desplazar
     * temporalmente las filas afectadas a valores negativos únicos para
     * evitar el conflicto del índice único mientras aplica el nuevo orden.
     * La regla de negocio (orden final siempre positivo) la valida el
     * dominio (RutaDetalle), no la columna.
     */
    public function up(): void
    {
        Schema::create('ruta_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('ruta_id');
            $table->unsignedBigInteger('proveedor_id');
            $table->integer('orden_visita');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('proveedor_id', 'uq_ruta_detalles_proveedor_id');
            $table->unique(['ruta_id', 'orden_visita'], 'uq_ruta_detalles_ruta_id_orden_visita');
            $table->index('ruta_id', 'idx_ruta_detalles_ruta_id');

            $table->foreign('ruta_id', 'fk_ruta_detalles_ruta_id')
                ->references('id')->on('rutas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('proveedor_id', 'fk_ruta_detalles_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_detalles');
    }
};
