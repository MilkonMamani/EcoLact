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
        Schema::create('insumo_movimientos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('insumo_id');
            $table->string('tipo', 20);
            $table->string('motivo', 30);
            $table->decimal('cantidad', 12, 2);
            $table->unsignedBigInteger('lote_produccion_id')->nullable();
            $table->dateTime('fecha');
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['insumo_id', 'fecha'], 'idx_insumo_movimientos_insumo_id_fecha');

            $table->foreign('insumo_id', 'fk_insumo_movimientos_insumo_id')
                ->references('id')->on('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('lote_produccion_id', 'fk_insumo_movimientos_lote_produccion_id')
                ->references('id')->on('lotes_produccion')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('registrado_por_user_id', 'fk_insumo_movimientos_registrado_por_user_id')
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
        Schema::dropIfExists('insumo_movimientos');
    }
};
