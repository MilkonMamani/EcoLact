<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Punto habitual de entrega del proveedor (puede estar junto a un
     * camino y no necesariamente en su vivienda). Nullable: no se inventan
     * coordenadas para proveedores antiguos que nunca las registraron.
     */
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('telefono');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->string('referencia_ubicacion', 255)->nullable()->after('longitud');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'referencia_ubicacion']);
        });
    }
};
