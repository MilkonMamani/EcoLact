<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_produccion_entregas', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('lote_produccion_id');
            $table->unsignedBigInteger('entrega_id');
            $table->decimal('litros_utilizados', 10, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['lote_produccion_id', 'entrega_id'], 'uq_lote_produccion_entregas_lote_produccion_id_entrega_id');

            $table->foreign('lote_produccion_id', 'fk_lote_produccion_entregas_lote_produccion_id')
                ->references('id')->on('lotes_produccion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('entrega_id', 'fk_lote_produccion_entregas_entrega_id')
                ->references('id')->on('entregas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_produccion_entregas');
    }
};
