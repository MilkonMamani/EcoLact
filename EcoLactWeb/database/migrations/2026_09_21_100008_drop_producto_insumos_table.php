<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `producto_insumos` (Producto -> Insumo -> cantidad por unidad) queda
 * reemplazada por `recetas` + `receta_detalles`.
 *
 * Solo se elimina si TODOS sus productos ya tienen una receta (la migración
 * backfill_recetas_desde_producto_insumos las crea). Si alguno no la tiene, se
 * aborta para no perder configuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_insumos')) {
            return;
        }

        $sinReceta = DB::table('producto_insumos')
            ->whereNotExists(function ($consulta): void {
                $consulta->selectRaw('1')
                    ->from('recetas')
                    ->whereColumn('recetas.producto_id', 'producto_insumos.producto_id');
            })
            ->distinct()
            ->count('producto_id');

        if ($sinReceta > 0) {
            throw new RuntimeException("Hay {$sinReceta} producto(s) en producto_insumos sin receta equivalente; no se elimina la tabla para no perder datos.");
        }

        Schema::drop('producto_insumos');
    }

    public function down(): void
    {
        if (Schema::hasTable('producto_insumos')) {
            return;
        }

        Schema::create('producto_insumos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('insumo_id');
            $table->decimal('cantidad_por_unidad', 12, 4);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['producto_id', 'insumo_id'], 'uq_producto_insumos_producto_id_insumo_id');

            $table->foreign('producto_id', 'fk_producto_insumos_producto_id')
                ->references('id')->on('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('insumo_id', 'fk_producto_insumos_insumo_id')
                ->references('id')->on('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
