<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\AcopiadoresListadoData;
use App\Application\Acopiador\Contracts\AcopiadoresListadoQuery;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Database\Query\Builder;

final class EloquentAcopiadoresListadoQuery implements AcopiadoresListadoQuery
{
    public function obtener(AcopiadoresFiltro $filtro): AcopiadoresListadoData
    {
        $kpis = [
            'activos' => (int) AcopiadorModel::where('estado', 'ACTIVO')->count(),
            'inactivos' => (int) AcopiadorModel::where('estado', 'INACTIVO')->count(),
            'zonasAsignadas' => (int) AcopiadorModel::query()->distinct('zona_id')->count('zona_id'),
            'conEntregas' => (int) AcopiadorModel::query()->whereHas('entregas')->count(),
        ];

        $filas = $this->base($filtro)
            ->select('a.id', 'a.nombres', 'a.apellidos', 'a.dni', 'a.telefono', 'a.estado', 'z.nombre as zona_nombre')
            ->selectRaw('(SELECT COUNT(*) FROM entregas AS e WHERE e.acopiador_id = a.id) as entregas_count')
            ->orderBy('a.nombres')
            ->orderBy('a.apellidos')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => trim($fila->nombres.' '.$fila->apellidos),
                'dni' => $fila->dni,
                'zonaNombre' => $fila->zona_nombre,
                'telefono' => $fila->telefono,
                'entregas' => (int) $fila->entregas_count,
                'estado' => $fila->estado,
            ])
            ->all();

        $zonas = ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all();

        return new AcopiadoresListadoData(kpis: $kpis, zonas: $zonas, filas: $filas);
    }

    private function base(AcopiadoresFiltro $filtro): Builder
    {
        $query = AcopiadorModel::query()->from('acopiadores as a')->toBase()
            ->join('zonas as z', 'z.id', '=', 'a.zona_id');

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $query->where(function (Builder $query) use ($busqueda): void {
                $query->where('a.nombres', 'like', $busqueda)
                    ->orWhere('a.apellidos', 'like', $busqueda)
                    ->orWhere('a.dni', 'like', $busqueda);
            });
        }

        if ($filtro->estado !== null) {
            $query->where('a.estado', $filtro->estado);
        }

        if ($filtro->zonaId !== null) {
            $query->where('a.zona_id', $filtro->zonaId);
        }

        return $query;
    }
}
