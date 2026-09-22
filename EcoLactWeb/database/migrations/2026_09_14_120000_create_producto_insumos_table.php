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
        Schema::create('producto_insumos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('insumo_id');
            $table->decimal('cantidad_por_unidad', 12, 4);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['producto_id', 'insumo_id'], 'uq_producto_insumos_producto_id_insumo_id');

            $table->foreign('producto_id', 'fk_producto_insumos_producto_id')
                ->references('id')->on('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('insumo_id', 'fk_producto_insumos_insumo_id')
                ->references('id')->on('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producto_insumos');
    }
};
