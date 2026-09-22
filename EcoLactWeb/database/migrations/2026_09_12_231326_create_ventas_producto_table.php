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
        Schema::create('ventas_producto', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('comprador_id')->nullable();
            $table->string('tipo_cliente', 20);
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('importe_total', 12, 2);
            $table->string('forma_pago', 20);
            $table->unsignedBigInteger('periodo_id')->nullable();
            $table->string('estado', 20);
            $table->dateTime('fecha');
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['proveedor_id', 'periodo_id'], 'idx_ventas_producto_proveedor_id_periodo_id');

            $table->foreign('producto_id', 'fk_ventas_producto_producto_id')
                ->references('id')->on('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('proveedor_id', 'fk_ventas_producto_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('comprador_id', 'fk_ventas_producto_comprador_id')
                ->references('id')->on('compradores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('periodo_id', 'fk_ventas_producto_periodo_id')
                ->references('id')->on('periodos_liquidacion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('registrado_por_user_id', 'fk_ventas_producto_registrado_por_user_id')
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
        Schema::dropIfExists('ventas_producto');
    }
};
