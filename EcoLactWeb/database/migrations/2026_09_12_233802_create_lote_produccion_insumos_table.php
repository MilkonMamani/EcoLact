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
        Schema::create('lote_produccion_insumos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('lote_produccion_id');
            $table->unsignedBigInteger('insumo_id');
            $table->decimal('cantidad_usada', 12, 4);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['lote_produccion_id', 'insumo_id'], 'uq_lote_produccion_insumos_lote_produccion_id_insumo_id');

            $table->foreign('lote_produccion_id', 'fk_lote_produccion_insumos_lote_produccion_id')
                ->references('id')->on('lotes_produccion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('insumo_id', 'fk_lote_produccion_insumos_insumo_id')
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
        Schema::dropIfExists('lote_produccion_insumos');
    }
};
