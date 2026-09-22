<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo esquema y propósito que proveedor_zona_historial: un registro de
     * auditoría, sin restricción única (la vigencia se gestiona por
     * aplicación cerrando fecha_fin antes de abrir un nuevo registro), de
     * cada asignación de un proveedor a una ruta a lo largo del tiempo.
     */
    public function up(): void
    {
        Schema::create('proveedor_ruta_historial', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('ruta_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['proveedor_id', 'fecha_fin'], 'idx_proveedor_ruta_historial_proveedor_id_fecha_fin');

            $table->foreign('proveedor_id', 'fk_proveedor_ruta_historial_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('ruta_id', 'fk_proveedor_ruta_historial_ruta_id')
                ->references('id')->on('rutas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_ruta_historial');
    }
};
