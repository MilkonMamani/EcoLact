<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbral del precio MAYORISTA: desde cuántas unidades compradas se aplica.
 * Solo tiene sentido en filas MAYORISTA (en las demás queda NULL) y se
 * versiona junto con el precio, igual que él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_precios', function (Blueprint $table) {
            $table->decimal('cantidad_minima', 10, 2)->nullable()->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('producto_precios', function (Blueprint $table) {
            $table->dropColumn('cantidad_minima');
        });
    }
};
