<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_calidad', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('codigo', 40);
            $table->string('nombre', 100);
            $table->string('unidad', 30)->nullable();
            $table->string('estado', 20);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('codigo', 'uq_parametros_calidad_codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_calidad');
    }
};
