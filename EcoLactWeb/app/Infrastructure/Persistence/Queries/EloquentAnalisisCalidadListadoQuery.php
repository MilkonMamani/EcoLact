<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\AnalisisCalidadListadoData;
use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class EloquentAnalisisCalidadListadoQuery implements AnalisisCalidadListadoQuery
{
    public function obtener(AnalisisCalidadFiltro $filtro): AnalisisCalidadListadoData
    {
        $porResultado = $this->base($filtro)
            ->select('a.resultado')
            ->selectRaw('COUNT(*) as cantidad')
            ->groupBy('a.resultado')
            ->get()
            ->keyBy('resultado');

        $kpis = [
            'total' => (int) $porResultado->sum('cantidad'),
            'aceptadas' => (int) ($porResultado->get(ResultadoCalidad::ACEPTADA->value)?->cantidad ?? 0),
            'observadas' => (int) ($porResultado->get(ResultadoCalidad::OBSERVADA->value)?->cantidad ?? 0),
            'rechazadas' => (int) ($porResultado->get(ResultadoCalidad::RECHAZADA->value)?->cantidad ?? 0),
        ];

        $filas = $this->base($filtro)
            ->select(
                'a.id',
                'a.fecha_analisis',
                'a.estado',
                'a.resultado',
                'p.nombres',
                'p.apellidos',
                'p.dni',
                'e.id as entrega_id',
                'e.litros',
                'z.nombre as zona_nombre',
                'u.username as confirmado_por',
            )
            ->orderByDesc('a.fecha_analisis')
            ->orderByDesc('a.id')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'fecha' => CarbonImmutable::parse($fila->fecha_analisis, config('app.timezone'))->format('d/m/Y H:i'),
                'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
                'proveedorDni' => $fila->dni,
                'entregaId' => (int) $fila->entrega_id,
                'zonaNombre' => $fila->zona_nombre,
                'resultado' => $fila->resultado,
                'estado' => $fila->estado,
                'confirmadoPor' => $fila->confirmado_por,
                // Para el móvil: litros de la entrega y la fecha en ISO (hora local del teléfono).
                'litros' => (float) $fila->litros,
                'fechaIso' => CarbonImmutable::parse($fila->fecha_analisis, config('app.timezone'))->format(DATE_ATOM),
            ])
            ->all();

        $zonas = ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])
            ->all();

        return new AnalisisCalidadListadoData(kpis: $kpis, zonas: $zonas, filas: $filas);
    }

    private function base(AnalisisCalidadFiltro $filtro): Builder
    {
        $query = AnalisisCalidadModel::query()->from('analisis_calidad as a')->toBase()
            ->join('entregas as e', 'e.id', '=', 'a.entrega_id')
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.confirmado_por_user_id');

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
            $query->where('a.resultado', $filtro->resultado);
        }

        if ($filtro->estado !== null) {
            $query->where('a.estado', $filtro->estado);
        }

        if ($filtro->fechaDesde !== null && $filtro->fechaHasta !== null) {
            $query->whereBetween('a.fecha_analisis', [
                $filtro->fechaDesde->toDateTimeString(),
                $filtro->fechaHasta->toDateTimeString(),
            ]);
        }

        return $query;
    }
}
