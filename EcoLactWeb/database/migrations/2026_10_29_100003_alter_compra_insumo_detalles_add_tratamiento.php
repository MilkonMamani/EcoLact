<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una línea de compra ya no es siempre un insumo: puede ser un gasto de
 * consumo inmediato (gaseosas), un bien o equipo (balanza) o un servicio
 * (luz). Solo las líneas INVENTARIO tienen insumo, presentación y cantidad en
 * unidad base, y solo ellas mueven stock.
 *
 *   tratamiento  INVENTARIO | CONSUMO | BIEN | SERVICIO (las existentes: INVENTARIO).
 *   descripcion  qué se compró, tal como se lee ("Gaseosa 3 L", "Sal").
 *   unidad       unidad libre de las líneas sin presentación ("und", "mes").
 *   subtotal     cantidad × precio unitario, guardado para no recalcular.
 *
 * Las columnas de insumo y presentación pasan a aceptar NULL; los datos
 * existentes no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra_insumo_detalles', function (Blueprint $table) {
            $table->string('tratamiento', 15)->default('INVENTARIO')->after('compra_insumo_id');
            $table->string('descripcion', 150)->nullable()->after('tratamiento');
            $table->string('unidad', 20)->nullable()->after('cantidad_presentaciones');
            $table->decimal('subtotal', 12, 2)->nullable()->after('precio_unitario');
        });

        Schema::table('compra_insumo_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('insumo_id')->nullable()->change();
            $table->string('presentacion_nombre', 60)->nullable()->change();
            $table->decimal('presentacion_cantidad', 12, 4)->nullable()->change();
            $table->string('presentacion_unidad', 10)->nullable()->change();
            $table->decimal('cantidad_base', 14, 4)->nullable()->change();
            $table->string('unidad_base', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volver a NOT NULL fallaría con líneas de gasto ya registradas: solo
        // se quitan las columnas nuevas.
        Schema::table('compra_insumo_detalles', function (Blueprint $table) {
            $table->dropColumn(['tratamiento', 'descripcion', 'unidad', 'subtotal']);
        });
    }
};
