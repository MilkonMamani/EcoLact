<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Al finalizar un lote se guarda, además de lo obtenido:
 *
 *  - produccion_detalles.merma: lo planificado que no se obtuvo
 *    (planificado - obtenido, nunca negativa). Se fija una sola vez al
 *    finalizar y no se recalcula después.
 *  - lotes_produccion.observacion_cierre: nota opcional de quien finaliza
 *    ("Se perdió una unidad durante el moldeado."). La observación de inicio
 *    sigue en `observacion`.
 *
 * Los lotes ya finalizados reciben su merma desde los valores que ya tenían.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->decimal('merma', 12, 2)->nullable()->after('cantidad_obtenida');
        });

        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->text('observacion_cierre')->nullable()->after('observacion');
        });

        DB::table('produccion_detalles')
            ->whereNotNull('cantidad_obtenida')
            ->update(['merma' => DB::raw('CASE WHEN cantidad_planificada > cantidad_obtenida THEN cantidad_planificada - cantidad_obtenida ELSE 0 END')]);
    }

    public function down(): void
    {
        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->dropColumn('observacion_cierre');
        });

        Schema::table('produccion_detalles', function (Blueprint $table) {
            $table->dropColumn('merma');
        });
    }
};
