<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un comprador registrado ya no es "mayorista" o "público": lo que hace
 * mayorista una venta es la cantidad (TarifasProducto). La columna se
 * conserva por los datos históricos, pero los compradores nuevos la dejan
 * en NULL y ningún precio se resuelve con ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compradores', function (Blueprint $table) {
            $table->string('tipo_cliente', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('compradores', function (Blueprint $table) {
            $table->string('tipo_cliente', 20)->nullable(false)->default('PUBLICO')->change();
        });
    }
};
