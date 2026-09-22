<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ajustes_liquidacion', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_producto_id')->nullable()->after('sancion_id');

            $table->foreign('venta_producto_id', 'fk_ajustes_liquidacion_venta_producto_id')
                ->references('id')->on('ventas_producto')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ajustes_liquidacion', function (Blueprint $table) {
            $table->dropForeign('fk_ajustes_liquidacion_venta_producto_id');
            $table->dropColumn('venta_producto_id');
        });
    }
};
