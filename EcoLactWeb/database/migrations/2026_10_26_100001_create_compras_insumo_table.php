<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compra física de insumos (desde una boleta escaneada o registrada a mano).
 * Los datos del comprobante son opcionales: el OCR los propone si los lee.
 * `registrado_por_user_id` es quien CONFIRMÓ la compra en EcoLact, no el
 * proveedor comercial. El stock no se toca aquí: cada detalle genera su
 * movimiento INGRESO_COMPRA (origen COMPRA_INSUMO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_insumo', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('origen', 10);
            $table->string('proveedor_comercial', 150)->nullable();
            $table->string('ruc', 11)->nullable();
            $table->string('numero_comprobante', 30)->nullable();
            $table->date('fecha');
            $table->decimal('total', 12, 2)->nullable();
            $table->string('imagen_boleta')->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamps();

            $table->index('fecha', 'idx_compras_insumo_fecha');

            $table->foreign('registrado_por_user_id', 'fk_compras_insumo_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_insumo');
    }
};
