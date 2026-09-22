<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->unsignedBigInteger('acopiador_id')->nullable();
            $table->unsignedBigInteger('zona_id');
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->dateTime('fecha_hora');
            $table->decimal('litros', 10, 2);
            $table->string('metodo_identificacion', 10);
            $table->string('estado', 25);
            $table->text('observacion')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['proveedor_id', 'fecha_hora'], 'idx_entregas_proveedor_id_fecha_hora');
            $table->index(['acopiador_id', 'fecha_hora'], 'idx_entregas_acopiador_id_fecha_hora');
            $table->index(['zona_id', 'fecha_hora'], 'idx_entregas_zona_id_fecha_hora');

            $table->foreign('proveedor_id', 'fk_entregas_proveedor_id')
                ->references('id')->on('proveedores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('acopiador_id', 'fk_entregas_acopiador_id')
                ->references('id')->on('acopiadores')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreign('zona_id', 'fk_entregas_zona_id')
                ->references('id')->on('zonas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('registrado_por_user_id', 'fk_entregas_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
