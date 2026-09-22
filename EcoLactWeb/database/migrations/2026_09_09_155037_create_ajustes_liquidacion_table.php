<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_liquidacion', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('sancion_id')->nullable();
            $table->string('tipo', 20);
            $table->string('descripcion', 255);
            $table->decimal('monto', 12, 2);
            $table->unsignedBigInteger('creado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('liquidacion_id', 'fk_ajustes_liquidacion_liquidacion_id')
                ->references('id')->on('liquidaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('sancion_id', 'fk_ajustes_liquidacion_sancion_id')
                ->references('id')->on('sanciones')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('creado_por_user_id', 'fk_ajustes_liquidacion_creado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_liquidacion');
    }
};
