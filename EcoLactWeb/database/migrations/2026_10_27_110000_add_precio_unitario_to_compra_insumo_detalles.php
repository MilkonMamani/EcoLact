<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio pagado por cada presentación comprada (S/ por saco, por balde...).
 * Opcional: si todas las líneas lo tienen y la compra no trae total, el total
 * de la compra es la suma de cantidad × precio unitario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_insumo_detalles', function (Blueprint $table) {
            $table->decimal('precio_unitario', 12, 2)->nullable()->after('cantidad_presentaciones');
        });
    }

    public function down(): void
    {
        Schema::table('compra_insumo_detalles', function (Blueprint $table) {
            $table->dropColumn('precio_unitario');
        });
    }
};
