<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El producto terminado nace desde su RECETA. Estas columnas guardan lo que el
 * administrador define al crear la receta:
 *
 *  - tipo_producto: Queso, Yogurt, Mantequilla...
 *  - nombre_base: "Queso Paria" (el nombre completo "Queso Paria 1 kg" sigue
 *    en `nombre`, que ya es único: así no hay productos duplicados aunque
 *    existan varias versiones de receta).
 *  - presentacion_cantidad / presentacion_unidad: 1 + kg.
 *  - imagen: ruta relativa en el disco público (products/...). Es la ÚNICA
 *    copia de la imagen: las recetas la muestran a través de su producto.
 *
 * Los productos existentes se conservan: su nombre_base es su nombre actual y
 * el resto queda vacío hasta que se configure su receta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('nombre_base', 120)->nullable()->after('nombre');
            $table->string('tipo_producto', 60)->nullable()->after('nombre_base');
            $table->decimal('presentacion_cantidad', 10, 3)->nullable()->after('tipo_producto');
            $table->string('presentacion_unidad', 20)->nullable()->after('presentacion_cantidad');
            $table->string('imagen', 255)->nullable()->after('presentacion_unidad');
        });

        DB::table('productos')->whereNull('nombre_base')->update(['nombre_base' => DB::raw('nombre')]);
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['nombre_base', 'tipo_producto', 'presentacion_cantidad', 'presentacion_unidad', 'imagen']);
        });
    }
};
