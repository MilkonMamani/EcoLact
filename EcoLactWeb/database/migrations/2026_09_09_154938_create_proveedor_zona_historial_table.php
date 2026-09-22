<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor_zona_historial', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('zona_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('proveedor_id', 'fk_proveedor_zona_historial_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('zona_id', 'fk_proveedor_zona_historial_zona_id')
                ->references('id')->on('zonas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_zona_historial');
    }
};
