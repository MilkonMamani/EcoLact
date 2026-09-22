<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Zona\Contracts\ZonasListadoQuery;
use App\Application\Zona\ZonasFiltro;
use App\Application\Zona\ZonasListadoData;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Database\Query\Builder;

final class EloquentZonasListadoQuery implements ZonasListadoQuery
{
    public function obtener(ZonasFiltro $filtro): ZonasListadoData
    {
        $kpis = [
            'activas' => (int) ZonaModel::where('estado', 'ACTIVO')->count(),
            'inactivas' => (int) ZonaModel::where('estado', 'INACTIVO')->count(),
            'proveedoresAsignados' => (int) ProveedorModel::whereNotNull('zona_id')->count(),
            'acopiadoresAsignados' => (int) AcopiadorModel::count(),
        ];

        $filas = $this->base($filtro)
            ->select('z.id', 'z.nombre', 'z.descripcion', 'z.estado')
            ->selectRaw('(SELECT COUNT(*) FROM proveedores AS p WHERE p.zona_id = z.id) as proveedores_count')
            ->selectRaw('(SELECT COUNT(*) FROM acopiadores AS a WHERE a.zona_id = z.id) as acopiadores_count')
            ->orderBy('z.nombre')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'descripcion' => $fila->descripcion,
                'proveedores' => (int) $fila->proveedores_count,
                'acopiadores' => (int) $fila->acopiadores_count,
                'estado' => $fila->estado,
            ])
            ->all();

        return new ZonasListadoData(kpis: $kpis, filas: $filas);
    }

    private function base(ZonasFiltro $filtro): Builder
    {
        $query = ZonaModel::query()->from('zonas as z')->toBase();

        if ($filtro->busqueda !== null) {
            $query->where('z.nombre', 'like', '%'.$filtro->busqueda.'%');
        }

        if ($filtro->estado !== null) {
            $query->where('z.estado', $filtro->estado);
        }

        return $query;
    }
}
