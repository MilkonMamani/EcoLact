<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\AcopiadoresListadoData;
use App\Application\Acopiador\AcopiadoresPaginaData;
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

        $filas = $this->filas($filtro)->get()->map(fn (object $fila): array => $this->fila($fila))->all();

        return new AcopiadoresListadoData(kpis: $kpis, zonas: $this->zonas(), filas: $filas);
    }

    public function paginar(AcopiadoresFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): AcopiadoresPaginaData
    {
        $filas = $this->filas($filtro)
            ->orderBy('a.id')
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString()
            ->through(fn (object $fila): array => $this->fila($fila));

        return new AcopiadoresPaginaData(zonas: $this->zonas(), zonasActivas: $this->zonas(soloActivas: true), filas: $filas);
    }

    /** El conteo de entregas es una subconsulta correlacionada: una sola consulta, sin N+1. */
    private function filas(AcopiadoresFiltro $filtro): Builder
    {
        return $this->base($filtro)
            ->select('a.id', 'a.nombres', 'a.apellidos', 'a.dni', 'a.telefono', 'a.estado', 'z.nombre as zona_nombre')
            ->selectRaw('(SELECT COUNT(*) FROM entregas AS e WHERE e.acopiador_id = a.id) as entregas_count')
            ->orderBy('a.nombres')
            ->orderBy('a.apellidos');
    }

    /** @return array{id: int, nombre: string, dni: string, zonaNombre: string, telefono: ?string, entregas: int, estado: string} */
    private function fila(object $fila): array
    {
        return [
            'id' => (int) $fila->id,
            'nombre' => trim($fila->nombres.' '.$fila->apellidos),
            'dni' => $fila->dni,
            'zonaNombre' => $fila->zona_nombre,
            'telefono' => $fila->telefono,
            'entregas' => (int) $fila->entregas_count,
            'estado' => $fila->estado,
        ];
    }

    /** @return list<array{id: int, nombre: string}> */
    private function zonas(bool $soloActivas = false): array
    {
        return ZonaModel::query()
            ->when($soloActivas, fn ($query) => $query->where('estado', 'ACTIVO'))
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])
            ->all();
    }

    private function base(AcopiadoresFiltro $filtro): Builder
    {
        $query = AcopiadorModel::query()->from('acopiadores as a')->toBase()
            ->join('zonas as z', 'z.id', '=', 'a.zona_id');

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $codigo = ltrim($filtro->busqueda, '#');

            $query->where(function (Builder $query) use ($busqueda, $codigo): void {
                $query->where('a.nombres', 'like', $busqueda)
                    ->orWhere('a.apellidos', 'like', $busqueda)
                    ->orWhere('a.dni', 'like', $busqueda)
                    ->orWhere('a.telefono', 'like', $busqueda);

                // El "código" del acopiador es su número de registro (columna #).
                if ($codigo !== '' && ctype_digit($codigo)) {
                    $query->orWhere('a.id', (int) $codigo);
                }
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
