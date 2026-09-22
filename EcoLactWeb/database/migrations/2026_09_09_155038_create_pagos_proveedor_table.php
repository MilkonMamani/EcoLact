<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_proveedor', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('liquidacion_id');
            $table->dateTime('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago', 30);
            $table->string('numero_operacion', 100)->nullable();
            $table->string('estado', 20);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('liquidacion_id', 'fk_pagos_proveedor_liquidacion_id')
                ->references('id')->on('liquidaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('registrado_por_user_id', 'fk_pagos_proveedor_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_proveedor');
    }
};
