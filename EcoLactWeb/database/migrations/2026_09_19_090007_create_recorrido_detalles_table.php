<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada fila es una "parada" congelada al crear el recorrido: copia de
     * proveedor_id/orden_visita/ubicación tomada de ruta_detalles y
     * proveedores en ese momento, para que el recorrido se siga viendo
     * igual aunque después cambien la ruta o la ubicación del proveedor.
     * ruta_detalle_id es nullable y con nullOnDelete: es solo una
     * referencia de trazabilidad hacia la asignación que originó la
     * parada, no una dependencia funcional del recorrido histórico.
     */
    public function up(): void
    {
        Schema::create('recorrido_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('recorrido_id');
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('ruta_detalle_id')->nullable();
            $table->unsignedInteger('orden_visita');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('referencia_ubicacion', 255)->nullable();
            $table->string('estado', 20);
            $table->text('observacion')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['recorrido_id', 'proveedor_id'], 'uq_recorrido_detalles_recorrido_id_proveedor_id');
            $table->index(['recorrido_id', 'estado'], 'idx_recorrido_detalles_recorrido_id_estado');

            $table->foreign('recorrido_id', 'fk_recorrido_detalles_recorrido_id')
                ->references('id')->on('recorridos_acopio')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('proveedor_id', 'fk_recorrido_detalles_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('ruta_detalle_id', 'fk_recorrido_detalles_ruta_detalle_id')
                ->references('id')->on('ruta_detalles')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorrido_detalles');
    }
};
