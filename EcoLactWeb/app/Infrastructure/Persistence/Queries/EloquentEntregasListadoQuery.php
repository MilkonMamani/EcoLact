<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\EntregasListadoData;
use App\Application\Acopio\EntregasPaginaData;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class EloquentEntregasListadoQuery implements EntregasListadoQuery
{
    public function obtener(EntregasFiltro $filtro): EntregasListadoData
    {
        $resumen = $this->base($filtro)
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value)
            ->selectRaw('COUNT(*) as entregas')
            ->selectRaw('COALESCE(SUM(e.litros), 0) as litros')
            ->selectRaw('SUM(CASE WHEN e.estado = ? THEN 1 ELSE 0 END) as pendientes', [EstadoEntrega::PENDIENTE_ANALISIS->value])
            ->selectRaw('SUM(CASE WHEN e.estado = ? THEN 1 ELSE 0 END) as analizadas', [EstadoEntrega::ANALIZADA->value])
            ->first();

        $kpis = [
            'litrosRegistrados' => round((float) $resumen->litros, 2),
            'entregas' => (int) $resumen->entregas,
            'pendientesAnalisis' => (int) $resumen->pendientes,
            'analizadas' => (int) $resumen->analizadas,
        ];

        $filas = $this->filas($filtro)->get()->map(fn (object $fila): array => $this->fila($fila))->all();

        return new EntregasListadoData(kpis: $kpis, zonas: $this->zonas(), acopiadores: $this->acopiadores(), filas: $filas);
    }

    public function paginar(EntregasFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): EntregasPaginaData
    {
        $filas = $this->filas($filtro)
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString()
            ->through(fn (object $fila): array => $this->fila($fila));

        return new EntregasPaginaData(zonas: $this->zonas(), acopiadores: $this->acopiadores(), filas: $filas);
    }

    /** Filas ya unidas a proveedor, zona, acopiador y análisis: una sola consulta, sin N+1. */
    private function filas(EntregasFiltro $filtro): Builder
    {
        return $this->base($filtro)
            ->select(
                'e.id',
                'e.fecha_hora',
                'e.litros',
                'e.metodo_identificacion',
                'e.estado',
                'p.nombres',
                'p.apellidos',
                'p.dni',
                'z.nombre as zona_nombre',
                'a.nombres as acopiador_nombres',
                'a.apellidos as acopiador_apellidos',
                'ac.resultado',
            )
            ->orderByDesc('e.fecha_hora')
            ->orderByDesc('e.id');
    }

    /** @return array{id: int, fecha: string, proveedorNombre: string, proveedorDni: string, zonaNombre: string, acopiadorNombre: string, litros: float, metodoIdentificacion: string, estado: string, resultado: ?string} */
    private function fila(object $fila): array
    {
        return [
            'id' => (int) $fila->id,
            'fecha' => CarbonImmutable::parse($fila->fecha_hora, config('app.timezone'))->format('d/m/Y H:i'),
            'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
            'proveedorDni' => $fila->dni,
            'zonaNombre' => $fila->zona_nombre,
            'acopiadorNombre' => $fila->acopiador_nombres !== null ? trim($fila->acopiador_nombres.' '.$fila->acopiador_apellidos) : '—',
            'litros' => (float) $fila->litros,
            'metodoIdentificacion' => $fila->metodo_identificacion,
            'estado' => $fila->estado,
            'resultado' => $fila->resultado,
        ];
    }

    /** @return list<array{id: int, nombre: string}> */
    private function zonas(): array
    {
        return ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all();
    }

    /** @return list<array{id: int, nombre: string}> */
    private function acopiadores(): array
    {
        return AcopiadorModel::query()->orderBy('nombres')->get(['id', 'nombres', 'apellidos'])
            ->map(fn (AcopiadorModel $acopiador): array => ['id' => (int) $acopiador->id, 'nombre' => trim($acopiador->nombres.' '.$acopiador->apellidos)])->all();
    }

    private function base(EntregasFiltro $filtro): Builder
    {
        $query = EntregaModel::query()->from('entregas as e')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('acopiadores as a', 'a.id', '=', 'e.acopiador_id')
            ->leftJoin('analisis_calidad as ac', 'ac.entrega_id', '=', 'e.id');

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

        if ($filtro->acopiadorId !== null) {
            $query->where('e.acopiador_id', $filtro->acopiadorId);
        }

        if ($filtro->estado !== null) {
            $query->where('e.estado', $filtro->estado);
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
