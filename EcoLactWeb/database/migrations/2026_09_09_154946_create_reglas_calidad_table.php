<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_calidad', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('parametro_id');
            $table->decimal('valor_minimo', 12, 4)->nullable();
            $table->decimal('valor_maximo', 12, 4)->nullable();
            $table->string('resultado_incumplimiento', 20);
            $table->unsignedTinyInteger('prioridad')->default(1);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('parametro_id', 'fk_reglas_calidad_parametro_id')
                ->references('id')->on('parametros_calidad')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_calidad');
    }
};
