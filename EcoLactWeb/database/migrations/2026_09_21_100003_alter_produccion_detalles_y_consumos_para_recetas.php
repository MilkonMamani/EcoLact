<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * produccion_detalles pasa a describir cada producto de un lote con lo
 * PLANIFICADO (al iniciar) y lo OBTENIDO realmente (al finalizar), y a
 * recordar qué receta/versión se usó. Los lotes históricos ya finalizados no
 * distinguían ambos valores: se les copia lo producido como planificado y
 * quedan sin receta (receta_id NULL).
 *
 * lote_produccion_insumos (snapshot de consumo por lote, ya existente) se
 * reutiliza como "produccion_consumos": gana cantidad_requerida (lo que
 * calculó la receta al iniciar) y unidad_base (la unidad en que se guardó).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('receta_id')->nullable()->after('producto_id');
            $table->unsignedInteger('receta_version')->nullable()->after('receta_id');
            $table->decimal('cantidad_planificada', 12, 2)->default(0)->after('receta_version');

            $table->foreign('receta_id', 'fk_produccion_detalles_receta_id')
                ->references('id')->on('recetas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        DB::table('produccion_detalles')->update(['cantidad_planificada' => DB::raw('cantidad_producida')]);

        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->renameColumn('cantidad_producida', 'cantidad_obtenida');
        });

        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_obtenida', 12, 2)->nullable()->change();
        });

        Schema::table('lote_produccion_insumos', function (Blueprint $table) {
            $table->decimal('cantidad_requerida', 14, 4)->default(0)->after('insumo_id');
            $table->string('unidad_base', 10)->nullable()->after('cantidad_usada');
        });

        DB::table('lote_produccion_insumos')->update(['cantidad_requerida' => DB::raw('cantidad_usada')]);

        foreach (DB::table('lote_produccion_insumos')->get(['id', 'insumo_id']) as $fila) {
            DB::table('lote_produccion_insumos')->where('id', $fila->id)->update([
                'unidad_base' => DB::table('insumos')->where('id', $fila->insumo_id)->value('unidad_base'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('lote_produccion_insumos', function (Blueprint $table) {
            $table->dropColumn(['cantidad_requerida', 'unidad_base']);
        });

        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_obtenida', 12, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->renameColumn('cantidad_obtenida', 'cantidad_producida');
            $table->dropForeign('fk_produccion_detalles_receta_id');
        });

        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->dropColumn(['receta_id', 'receta_version', 'cantidad_planificada']);
        });
    }
};
