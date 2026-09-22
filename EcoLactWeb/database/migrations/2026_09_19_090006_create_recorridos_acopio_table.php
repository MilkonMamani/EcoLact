<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorridos_acopio', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('ruta_id');
            $table->unsignedBigInteger('acopiador_id');
            $table->date('fecha_operativa');
            $table->string('estado', 20);
            $table->dateTime('iniciado_en')->nullable();
            $table->dateTime('finalizado_en')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['ruta_id', 'fecha_operativa'], 'idx_recorridos_acopio_ruta_id_fecha_operativa');
            $table->index(['acopiador_id', 'fecha_operativa'], 'idx_recorridos_acopio_acopiador_id_fecha_operativa');
            $table->index('estado', 'idx_recorridos_acopio_estado');

            $table->foreign('ruta_id', 'fk_recorridos_acopio_ruta_id')
                ->references('id')->on('rutas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('acopiador_id', 'fk_recorridos_acopio_acopiador_id')
                ->references('id')->on('acopiadores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorridos_acopio');
    }
};
