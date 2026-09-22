<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisis_calidad', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('entrega_id');
            $table->string('imagen_path', 500)->nullable();
            $table->longText('ocr_texto_original')->nullable();
            $table->decimal('ocr_confianza', 5, 2)->nullable();
            $table->string('estado', 20);
            $table->string('resultado', 20);
            $table->dateTime('fecha_analisis');
            $table->unsignedBigInteger('confirmado_por_user_id')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('entrega_id', 'uq_analisis_calidad_entrega_id');
            $table->index(['resultado', 'fecha_analisis'], 'idx_analisis_calidad_resultado_fecha_analisis');

            $table->foreign('entrega_id', 'fk_analisis_calidad_entrega_id')
                ->references('id')->on('entregas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('confirmado_por_user_id', 'fk_analisis_calidad_confirmado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_calidad');
    }
};
