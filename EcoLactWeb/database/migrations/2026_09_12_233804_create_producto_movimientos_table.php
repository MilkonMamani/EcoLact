<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('producto_movimientos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('tipo', 20);
            $table->decimal('cantidad', 12, 2);
            $table->unsignedBigInteger('lote_produccion_id')->nullable();
            $table->unsignedBigInteger('venta_producto_id')->nullable();
            $table->dateTime('fecha');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['producto_id', 'fecha'], 'idx_producto_movimientos_producto_id_fecha');

            $table->foreign('producto_id', 'fk_producto_movimientos_producto_id')
                ->references('id')->on('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('lote_produccion_id', 'fk_producto_movimientos_lote_produccion_id')
                ->references('id')->on('lotes_produccion')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('venta_producto_id', 'fk_producto_movimientos_venta_producto_id')
                ->references('id')->on('ventas_producto')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('registrado_por_user_id', 'fk_producto_movimientos_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_movimientos');
    }
};
