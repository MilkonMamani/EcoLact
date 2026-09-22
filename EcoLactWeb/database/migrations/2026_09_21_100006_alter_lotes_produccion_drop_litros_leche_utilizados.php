<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La leche utilizada por un lote ya no es un dato manual independiente: se
 * deriva del consumo real del insumo LECHE registrado en
 * lote_produccion_insumos. La migración anterior copió los litros históricos
 * a esa tabla, por lo que la columna puede eliminarse sin perder datos.
 *
 * Guarda de seguridad: si algún lote con litros no tiene su consumo de leche
 * registrado, se aborta para no perder información.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lotes_produccion', 'litros_leche_utilizados')) {
            return;
        }

        $lecheId = DB::table('insumos')->where('codigo', 'LECHE')->value('id');

        $sinRespaldo = DB::table('lotes_produccion')
            ->where('litros_leche_utilizados', '>', 0)
            ->whereNotExists(function ($consulta) use ($lecheId): void {
                $consulta->selectRaw('1')
                    ->from('lote_produccion_insumos as lpi')
                    ->whereColumn('lpi.lote_produccion_id', 'lotes_produccion.id')
                    ->where('lpi.insumo_id', $lecheId);
            })
            ->count();

        if ($sinRespaldo > 0) {
            throw new RuntimeException("Hay {$sinRespaldo} lote(s) con litros de leche sin consumo de Leche registrado; no se elimina lotes_produccion.litros_leche_utilizados para no perder datos.");
        }

        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->dropColumn('litros_leche_utilizados');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('lotes_produccion', 'litros_leche_utilizados')) {
            return;
        }

        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->decimal('litros_leche_utilizados', 12, 2)->default(0)->after('fecha_hora_fin');
        });

        $lecheId = DB::table('insumos')->where('codigo', 'LECHE')->value('id');

        if ($lecheId === null) {
            return;
        }

        foreach (DB::table('lote_produccion_insumos')->where('insumo_id', $lecheId)->get() as $consumo) {
            DB::table('lotes_produccion')->where('id', $consumo->lote_produccion_id)->update([
                'litros_leche_utilizados' => $consumo->cantidad_usada,
            ]);
        }
    }
};
