<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Proveedor\ProveedoresListadoData;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Database\Query\Builder;

final class EloquentProveedoresListadoQuery implements ProveedoresListadoQuery
{
    public function obtener(ProveedoresFiltro $filtro): ProveedoresListadoData
    {
        $kpis = [
            'activos' => (int) ProveedorModel::where('estado', 'ACTIVO')->count(),
            'inactivos' => (int) ProveedorModel::where('estado', 'INACTIVO')->count(),
            'zonasConProveedores' => (int) ProveedorModel::query()->distinct('zona_id')->count('zona_id'),
            'requierenSeguimiento' => (int) ProveedorModel::where('advertencias_count', '>', 0)->count(),
        ];

        $filas = $this->base($filtro)
            ->select('p.id', 'p.nombres', 'p.apellidos', 'p.dni', 'p.telefono', 'p.advertencias_count', 'p.estado', 'z.nombre as zona_nombre')
            ->orderBy('p.nombres')
            ->orderBy('p.apellidos')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => trim($fila->nombres.' '.$fila->apellidos),
                'dni' => $fila->dni,
                'zonaNombre' => $fila->zona_nombre ?? 'Sin asignar',
                'telefono' => $fila->telefono,
                'advertencias' => (int) $fila->advertencias_count,
                'estado' => $fila->estado,
            ])
            ->all();

        $zonas = ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all();

        return new ProveedoresListadoData(kpis: $kpis, zonas: $zonas, filas: $filas);
    }

    private function base(ProveedoresFiltro $filtro): Builder
    {
        $query = ProveedorModel::query()->from('proveedores as p')->toBase()
            ->leftJoin('zonas as z', 'z.id', '=', 'p.zona_id');

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $query->where(function (Builder $query) use ($busqueda): void {
                $query->where('p.nombres', 'like', $busqueda)
                    ->orWhere('p.apellidos', 'like', $busqueda)
                    ->orWhere('p.dni', 'like', $busqueda);
            });
        }

        if ($filtro->estado !== null) {
            $query->where('p.estado', $filtro->estado);
        }

        if ($filtro->zonaId !== null) {
            $query->where('p.zona_id', $filtro->zonaId);
        }

        if ($filtro->seguimiento === 'con_seguimiento') {
            $query->where('p.advertencias_count', '>', 0);
        } elseif ($filtro->seguimiento === 'sin_seguimiento') {
            $query->where('p.advertencias_count', 0);
        }

        return $query;
    }
}
