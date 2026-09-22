<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Produccion\Contracts\LotesProduccionListadoQuery;
use App\Application\Produccion\LotesProduccionFiltro;
use App\Application\Produccion\LotesProduccionListadoData;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentLotesProduccionListadoQuery implements LotesProduccionListadoQuery
{
    public function obtener(LotesProduccionFiltro $filtro): LotesProduccionListadoData
    {
        $loteIds = $this->base($filtro)->pluck('l.id');

        // La leche utilizada sale del consumo real del insumo LECHE registrado
        // en cada lote, nunca de un valor escrito a mano.
        $kpis = [
            'enProceso' => (int) LoteProduccionModel::where('estado', 'EN_PROCESO')->count(),
            'finalizados' => (int) $this->base($filtro)->where('l.estado', 'FINALIZADO')->count(),
            'lecheUtilizada' => round((float) $this->consumoDeLeche()->whereIn('c.lote_produccion_id', $loteIds)->sum('c.cantidad_usada'), 2),
            'productosObtenidos' => round((float) DB::table('produccion_detalles')
                ->whereIn('lote_produccion_id', $loteIds)
                ->sum('cantidad_obtenida'), 2),
        ];

        $filas = $this->base($filtro)
            ->select('l.id', 'l.codigo', 'l.fecha_hora_inicio', 'l.fecha_hora_fin', 'l.estado')
            ->selectRaw("(SELECT COALESCE(SUM(c.cantidad_usada), 0) FROM lote_produccion_insumos AS c INNER JOIN insumos AS i ON i.id = c.insumo_id WHERE c.lote_produccion_id = l.id AND i.codigo = 'LECHE') as litros_leche")
            ->selectRaw('(SELECT COUNT(*) FROM produccion_detalles AS d WHERE d.lote_produccion_id = l.id) as productos_count')
            ->selectRaw('(SELECT SUM(d.cantidad_obtenida) FROM produccion_detalles AS d WHERE d.lote_produccion_id = l.id) as productos_obtenidos')
            ->orderByDesc('l.fecha_hora_inicio')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'codigo' => $fila->codigo,
                'inicio' => CarbonImmutable::parse($fila->fecha_hora_inicio, config('app.timezone'))->format('d/m/Y H:i'),
                'fin' => $fila->fecha_hora_fin !== null ? CarbonImmutable::parse($fila->fecha_hora_fin, config('app.timezone'))->format('d/m/Y H:i') : null,
                'litros' => round((float) $fila->litros_leche, 2),
                'productos' => (int) $fila->productos_count,
                'obtenido' => $fila->productos_obtenidos !== null ? round((float) $fila->productos_obtenidos, 2) : null,
                'estado' => $fila->estado,
            ])
            ->all();

        return new LotesProduccionListadoData(kpis: $kpis, filas: $filas);
    }

    private function consumoDeLeche(): Builder
    {
        return DB::table('lote_produccion_insumos as c')
            ->join('insumos as i', 'i.id', '=', 'c.insumo_id')
            ->where('i.codigo', 'LECHE');
    }

    private function base(LotesProduccionFiltro $filtro): Builder
    {
        $query = LoteProduccionModel::query()->from('lotes_produccion as l')->toBase();

        if ($filtro->busqueda !== null) {
            $query->where('l.codigo', 'like', '%'.$filtro->busqueda.'%');
        }

        if ($filtro->estado !== null) {
            $query->where('l.estado', $filtro->estado);
        }

        if ($filtro->fechaDesde !== null && $filtro->fechaHasta !== null) {
            $query->whereBetween('l.fecha_hora_inicio', [
                $filtro->fechaDesde->toDateTimeString(),
                $filtro->fechaHasta->toDateTimeString(),
            ]);
        }

        return $query;
    }
}
