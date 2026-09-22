<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes_produccion', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('codigo', 50);
            $table->dateTime('fecha_hora_inicio');
            $table->dateTime('fecha_hora_fin')->nullable();
            $table->decimal('litros_leche_utilizados', 12, 2);
            $table->string('estado', 20);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('codigo', 'uq_lotes_produccion_codigo');

            $table->foreign('registrado_por_user_id', 'fk_lotes_produccion_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes_produccion');
    }
};
