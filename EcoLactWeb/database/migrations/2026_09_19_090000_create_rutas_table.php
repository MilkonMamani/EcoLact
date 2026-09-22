<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('zona_id');
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['zona_id', 'nombre'], 'uq_rutas_zona_id_nombre');
            $table->index(['zona_id', 'estado'], 'idx_rutas_zona_id_estado');

            $table->foreign('zona_id', 'fk_rutas_zona_id')
                ->references('id')->on('zonas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
