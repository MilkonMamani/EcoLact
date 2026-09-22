<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicados', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('titulo', 180);
            $table->text('contenido');
            $table->string('tipo', 30);
            $table->string('alcance', 20);
            $table->unsignedBigInteger('zona_id')->nullable();
            $table->dateTime('fecha_publicacion');
            $table->dateTime('fecha_evento')->nullable();
            $table->unsignedBigInteger('creado_por_user_id');
            $table->string('estado', 20);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('zona_id', 'fk_comunicados_zona_id')
                ->references('id')->on('zonas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('creado_por_user_id', 'fk_comunicados_creado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicados');
    }
};
