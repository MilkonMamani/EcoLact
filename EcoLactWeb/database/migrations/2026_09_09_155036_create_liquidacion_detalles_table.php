<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('valorizacion_entrega_id');
            $table->decimal('importe', 12, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('valorizacion_entrega_id', 'uq_liquidacion_detalles_valorizacion_entrega_id');

            $table->foreign('liquidacion_id', 'fk_liquidacion_detalles_liquidacion_id')
                ->references('id')->on('liquidaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('valorizacion_entrega_id', 'fk_liquidacion_detalles_valorizacion_entrega_id')
                ->references('id')->on('valorizaciones_entrega')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_detalles');
    }
};
