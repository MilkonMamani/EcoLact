<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisis_calidad_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('analisis_id');
            $table->unsignedBigInteger('parametro_id');
            $table->decimal('valor', 12, 4);
            $table->string('origen', 10);
            $table->decimal('confianza_ocr', 5, 2)->nullable();
            $table->boolean('cumple')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['analisis_id', 'parametro_id'], 'uq_analisis_calidad_detalles_analisis_id_parametro_id');

            $table->foreign('analisis_id', 'fk_analisis_calidad_detalles_analisis_id')
                ->references('id')->on('analisis_calidad')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('parametro_id', 'fk_analisis_calidad_detalles_parametro_id')
                ->references('id')->on('parametros_calidad')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_calidad_detalles');
    }
};
