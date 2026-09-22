<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `lote_produccion_entregas` enlazaba un lote con entregas concretas, pero
 * ningún caso de uso escribió jamás en ella (la leche se junta en un solo
 * recipiente). Con la nueva lógica la trazabilidad es ENTREGA -> movimiento
 * INGRESO_ACOPIO de Leche y LOTE -> movimiento CONSUMO_PRODUCCION de Leche.
 *
 * Solo se elimina si está vacía; si tuviera filas se aborta para no perder
 * historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lote_produccion_entregas')) {
            return;
        }

        $filas = DB::table('lote_produccion_entregas')->count();

        if ($filas > 0) {
            throw new RuntimeException("lote_produccion_entregas tiene {$filas} fila(s); no se elimina para no perder historial.");
        }

        Schema::drop('lote_produccion_entregas');
    }

    public function down(): void
    {
        if (Schema::hasTable('lote_produccion_entregas')) {
            return;
        }

        Schema::create('lote_produccion_entregas', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('lote_produccion_id');
            $table->unsignedBigInteger('entrega_id');
            $table->decimal('litros_utilizados', 10, 2);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['lote_produccion_id', 'entrega_id'], 'uq_lote_produccion_entregas_lote_produccion_id_entrega_id');

            $table->foreign('lote_produccion_id', 'fk_lote_produccion_entregas_lote_produccion_id')
                ->references('id')->on('lotes_produccion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('entrega_id', 'fk_lote_produccion_entregas_entrega_id')
                ->references('id')->on('entregas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
