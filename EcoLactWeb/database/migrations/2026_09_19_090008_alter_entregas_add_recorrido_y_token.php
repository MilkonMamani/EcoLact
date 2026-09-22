<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * recorrido_detalle_id enlaza una entrega con la parada del recorrido
     * que la originó (nullable: las entregas registradas fuera de un
     * recorrido, por ejemplo por CALIDAD o por el flujo manual existente,
     * siguen sin ese vínculo). token_idempotencia es la defensa real contra
     * doble envío/reintento/concurrencia: el INSERT choca contra el índice
     * único si dos peticiones con el mismo token llegan a la vez, sin
     * bloquear entregas legítimas distintas del mismo proveedor.
     */
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->unsignedBigInteger('recorrido_detalle_id')->nullable()->after('acopiador_id');
            $table->string('token_idempotencia', 64)->nullable()->after('metodo_identificacion');

            $table->unique('token_idempotencia', 'uq_entregas_token_idempotencia');
            $table->index('recorrido_detalle_id', 'idx_entregas_recorrido_detalle_id');

            $table->foreign('recorrido_detalle_id', 'fk_entregas_recorrido_detalle_id')
                ->references('id')->on('recorrido_detalles')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign('fk_entregas_recorrido_detalle_id');
            $table->dropUnique('uq_entregas_token_idempotencia');
            $table->dropColumn(['recorrido_detalle_id', 'token_idempotencia']);
        });
    }
};
