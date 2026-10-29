<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos de una compra (boleta, factura, recibo, constancia de
 * transferencia, nota de crédito). UN archivo, UNA fila: Caja e Inventario
 * lo muestran desde aquí, nunca con una copia.
 *
 * Los nuevos se guardan en el disco privado (`local`) y solo se sirven por
 * CompraController::verComprobante con autorización. Las boletas históricas
 * siguen donde estaban (disco `public`, compras-insumo/boletas): se
 * referencian, no se mueven ni se duplican.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_compra', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('compra_insumo_id');
            $table->string('tipo', 30);
            $table->string('disco', 20);
            $table->string('ruta', 255);
            $table->string('nombre_original', 150)->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('tamano')->nullable();
            $table->boolean('procesado_ocr')->default(false);
            $table->unsignedBigInteger('subido_por_user_id');
            $table->timestamps();

            $table->unique(['disco', 'ruta'], 'uq_comprobantes_compra_disco_ruta');
            $table->index('compra_insumo_id', 'idx_comprobantes_compra_compra');

            $table->foreign('compra_insumo_id', 'fk_comprobantes_compra_compra_insumo_id')
                ->references('id')->on('compras_insumo')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('subido_por_user_id', 'fk_comprobantes_compra_subido_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_compra');
    }
};
