<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiendas y empresas a las que EcoLact COMPRA (Distribuidora San José,
 * ferretería, empresa de luz...). Nada que ver con `proveedores`, que son los
 * productores de leche de Acopio: tablas, pantallas y pagos separados.
 *
 * El RUC es opcional (una bodega puede no darlo) pero, si está, es único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores_comerciales', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('razon_social', 150);
            $table->string('ruc', 11)->nullable()->unique('uq_proveedores_comerciales_ruc');
            $table->string('estado', 10)->default('ACTIVO');
            $table->timestamps();

            $table->index('razon_social', 'idx_proveedores_comerciales_razon_social');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores_comerciales');
    }
};
