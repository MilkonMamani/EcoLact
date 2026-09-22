<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produccion_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('lote_produccion_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad_producida', 12, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['lote_produccion_id', 'producto_id'], 'uq_produccion_detalles_lote_produccion_id_producto_id');

            $table->foreign('lote_produccion_id', 'fk_produccion_detalles_lote_produccion_id')
                ->references('id')->on('lotes_produccion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('producto_id', 'fk_produccion_detalles_producto_id')
                ->references('id')->on('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_detalles');
    }
};
