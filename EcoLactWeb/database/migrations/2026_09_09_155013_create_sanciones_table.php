<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanciones', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('analisis_id')->nullable();
            $table->string('tipo', 40);
            $table->text('descripcion');
            $table->string('tipo_penalizacion', 30);
            $table->decimal('valor_penalizacion', 12, 2)->nullable();
            $table->date('fecha');
            $table->string('estado', 20);
            $table->unsignedBigInteger('creado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('proveedor_id', 'fk_sanciones_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('analisis_id', 'fk_sanciones_analisis_id')
                ->references('id')->on('analisis_calidad')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('creado_por_user_id', 'fk_sanciones_creado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanciones');
    }
};
