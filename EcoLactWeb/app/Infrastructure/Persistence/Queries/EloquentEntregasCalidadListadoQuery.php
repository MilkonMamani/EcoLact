<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\EntregasCalidadListadoQuery;
use App\Application\Calidad\EntregasCalidadListadoData;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * Calidad parte de las ENTREGAS (LEFT JOIN a analisis_calidad), no de los
 * análisis: así las entregas sin análisis aparecen como Pendiente sin crear un
 * análisis ficticio. El análisis sigue siendo opcional: Pendiente solo significa
 * "todavía no tiene análisis de calidad" y no bloquea el pago ni el stock.
 */
final class EloquentEntregasCalidadListadoQuery implements EntregasCalidadListadoQuery
{
    public function obtener(AnalisisCalidadFiltro $filtro): EntregasCalidadListadoData
    {
        $confirmado = EstadoAnalisis::CONFIRMADO->value;

        $totales = $this->base($filtro)
            ->selectRaw('COUNT(CASE WHEN a.id IS NULL OR a.estado <> ? THEN 1 END) as pendientes', [$confirmado])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? THEN 1 END) as analizadas', [$confirmado])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 END) as aceptadas', [$confirmado, ResultadoCalidad::ACEPTADA->value])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 END) as observadas', [$confirmado, ResultadoCalidad::OBSERVADA->value])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 END) as rechazadas', [$confirmado, ResultadoCalidad::RECHAZADA->value])
            ->first();

        $filas = $this->base($filtro)
            ->select('e.id as entrega_id', 'e.fecha_hora', 'e.litros', 'p.nombres', 'p.apellidos', 'p.dni', 'z.nombre as zona_nombre', 'a.id as analisis_id', 'a.estado', 'a.resultado')
            ->orderByDesc('e.fecha_hora')
            ->orderByDesc('e.id')
            ->get()
            ->map(fn (object $fila): array => [
                'entregaId' => (int) $fila->entrega_id,
                'analisisId' => $fila->analisis_id !== null ? (int) $fila->analisis_id : null,
                'fecha' => CarbonImmutable::parse($fila->fecha_hora, config('app.timezone'))->format('d/m/Y H:i'),
                'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
                'proveedorDni' => $fila->dni,
                'zonaNombre' => $fila->zona_nombre,
                'resultado' => $fila->estado === $confirmado ? $fila->resultado : null,
                'estado' => $fila->estado ?? 'PENDIENTE',
                'litros' => (float) $fila->litros,
            ])
            ->all();

        $zonas = ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])
            ->all();

        return new EntregasCalidadListadoData(
            kpis: [
                'pendientes' => (int) $totales->pendientes,
                'analizadas' => (int) $totales->analizadas,
                'aceptadas' => (int) $totales->aceptadas,
                'observadas' => (int) $totales->observadas,
                'rechazadas' => (int) $totales->rechazadas,
            ],
            zonas: $zonas,
            filas: $filas,
        );
    }

    private function base(AnalisisCalidadFiltro $filtro): Builder
    {
        $query = EntregaModel::query()->from('entregas as e')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('analisis_calidad as a', 'a.entrega_id', '=', 'e.id')
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value);

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $query->where(function (Builder $query) use ($busqueda, $filtro): void {
                $query->where('p.nombres', 'like', $busqueda)
                    ->orWhere('p.apellidos', 'like', $busqueda)
                    ->orWhere('p.dni', 'like', $busqueda)
                    ->orWhere('e.id', $filtro->busqueda);
            });
        }

        if ($filtro->zonaId !== null) {
            $query->where('e.zona_id', $filtro->zonaId);
        }

        if ($filtro->resultado !== null) {
            $query->where('a.estado', EstadoAnalisis::CONFIRMADO->value)->where('a.resultado', $filtro->resultado);
        }

        if ($filtro->estado === 'PENDIENTE') {
            $query->whereNull('a.id');
        } elseif ($filtro->estado !== null) {
            $query->where('a.estado', $filtro->estado);
        }

        if ($filtro->fechaDesde !== null && $filtro->fechaHasta !== null) {
            $query->whereBetween('e.fecha_hora', [
                $filtro->fechaDesde->toDateTimeString(),
                $filtro->fechaHasta->toDateTimeString(),
            ]);
        }

        return $query;
    }
}
