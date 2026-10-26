<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Produccion\Contracts\LotesProduccionListadoQuery;
use App\Application\Produccion\LotesProduccionFiltro;
use App\Application\Produccion\LotesProduccionListadoData;
use App\Domain\Produccion\Producto;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentLotesProduccionListadoQuery implements LotesProduccionListadoQuery
{
    public function obtener(LotesProduccionFiltro $filtro): LotesProduccionListadoData
    {
        // Solo las métricas que muestra la pantalla; la leche disponible sale
        // del stock real del insumo LECHE (ObtenerStockLeche), no de aquí.
        $kpis = [
            'enProceso' => (int) LoteProduccionModel::where('estado', 'EN_PROCESO')->count(),
            'finalizados' => (int) $this->base($filtro)->where('l.estado', 'FINALIZADO')->count(),
        ];

        // La leche utilizada sale del consumo real del insumo LECHE registrado
        // en cada lote, nunca de un valor escrito a mano.
        $filas = $this->base($filtro)
            ->select('l.id', 'l.codigo', 'l.fecha_hora_inicio', 'l.fecha_hora_fin', 'l.estado')
            ->selectRaw("(SELECT COALESCE(SUM(c.cantidad_usada), 0) FROM lote_produccion_insumos AS c INNER JOIN insumos AS i ON i.id = c.insumo_id WHERE c.lote_produccion_id = l.id AND i.codigo = 'LECHE') as litros_leche")
            ->selectRaw('(SELECT COUNT(*) FROM produccion_detalles AS d WHERE d.lote_produccion_id = l.id) as productos_count')
            ->selectRaw('(SELECT SUM(d.cantidad_obtenida) FROM produccion_detalles AS d WHERE d.lote_produccion_id = l.id) as productos_obtenidos')
            ->orderByDesc('l.fecha_hora_inicio')
            ->get();

        $productosPorLote = $this->productosPorLote($filas->pluck('id')->all());

        $filas = $filas
            ->map(function (object $fila) use ($productosPorLote): array {
                $productos = $productosPorLote[(int) $fila->id] ?? [];
                $unidades = array_unique(array_column($productos, 'unidad'));

                return [
                    'id' => (int) $fila->id,
                    'codigo' => $fila->codigo,
                    'inicio' => CarbonImmutable::parse($fila->fecha_hora_inicio, config('app.timezone'))->format('d/m/Y H:i'),
                    'fin' => $fila->fecha_hora_fin !== null ? CarbonImmutable::parse($fila->fecha_hora_fin, config('app.timezone'))->format('d/m/Y H:i') : null,
                    'litros' => round((float) $fila->litros_leche, 2),
                    'productos' => (int) $fila->productos_count,
                    'productosNombres' => array_column($productos, 'etiqueta'),
                    // Solo se puede sumar lo obtenido con una unidad si todos los productos la comparten.
                    'unidadObtenido' => count($unidades) === 1 ? $unidades[0] : null,
                    'obtenido' => $fila->productos_obtenidos !== null ? round((float) $fila->productos_obtenidos, 2) : null,
                    'estado' => $fila->estado,
                ];
            })
            ->all();

        return new LotesProduccionListadoData(kpis: $kpis, filas: $filas);
    }

    /**
     * Productos de todos los lotes listados en UNA consulta (sin N+1), con su
     * etiqueta "Queso Paria · 1 kg".
     *
     * @param  list<int>  $loteIds
     * @return array<int, list<array{etiqueta: string, unidad: string}>>
     */
    private function productosPorLote(array $loteIds): array
    {
        if ($loteIds === []) {
            return [];
        }

        $porLote = [];

        $filas = DB::table('produccion_detalles as d')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->whereIn('d.lote_produccion_id', $loteIds)
            ->orderBy('d.id')
            ->get(['d.lote_produccion_id', 'p.nombre', 'p.nombre_base', 'p.presentacion_cantidad', 'p.presentacion_unidad', 'p.unidad_medida']);

        foreach ($filas as $fila) {
            $producto = new Producto(
                id: null,
                nombre: $fila->nombre,
                unidadMedida: $fila->unidad_medida,
                estado: 'ACTIVO',
                nombreBase: $fila->nombre_base,
                presentacionCantidad: $fila->presentacion_cantidad !== null ? (float) $fila->presentacion_cantidad : null,
                presentacionUnidad: $fila->presentacion_unidad,
            );

            $porLote[(int) $fila->lote_produccion_id][] = ['etiqueta' => $producto->etiqueta(), 'unidad' => $producto->unidadMedida()];
        }

        return $porLote;
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
