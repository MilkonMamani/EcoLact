<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Proveedor\ProveedoresListadoData;
use App\Application\Proveedor\ProveedoresPaginaData;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

        return new ProveedoresListadoData(kpis: $kpis, zonas: $this->zonas(), filas: $filas);
    }

    /**
     * La última entrega y los litros acumulados salen de subconsultas
     * correlacionadas (índice entregas.proveedor_id + fecha_hora): se
     * calculan solo para las filas de la página, en la misma consulta, sin
     * N+1. Las entregas anuladas no cuentan.
     */
    public function paginar(ProveedoresFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): ProveedoresPaginaData
    {
        $entregasValidas = fn (): Builder => DB::table('entregas as e')
            ->whereColumn('e.proveedor_id', 'p.id')
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value);

        $filas = $this->base($filtro)
            ->select('p.id', 'p.nombres', 'p.apellidos', 'p.dni', 'p.telefono', 'p.estado', 'z.nombre as zona_nombre')
            ->selectSub($entregasValidas()->selectRaw('MAX(e.fecha_hora)'), 'ultima_entrega')
            ->selectSub($entregasValidas()->selectRaw('COALESCE(SUM(e.litros), 0)'), 'total_litros')
            ->orderBy('p.nombres')
            ->orderBy('p.apellidos')
            ->orderBy('p.id')
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString()
            ->through(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => trim($fila->nombres.' '.$fila->apellidos),
                'dni' => $fila->dni,
                'telefono' => $fila->telefono,
                'zonaNombre' => $fila->zona_nombre,
                'ultimaEntrega' => $fila->ultima_entrega !== null
                    ? CarbonImmutable::parse($fila->ultima_entrega, config('app.timezone'))->format('d/m/Y')
                    : null,
                'totalLitros' => round((float) $fila->total_litros, 2),
                'estado' => $fila->estado,
            ]);

        return new ProveedoresPaginaData(zonas: $this->zonas(), filas: $filas, zonasReincorporacion: $this->zonasReincorporacion());
    }

    /**
     * Zonas activas con sus rutas activas, en dos consultas (sin N+1). La
     * zona de un proveedor se deriva de su ruta, así que para reactivarlo en
     * una zona hace falta elegir también una de sus rutas.
     *
     * @return list<array{id: int, nombre: string, rutas: list<array{id: int, nombre: string}>}>
     */
    private function zonasReincorporacion(): array
    {
        return ZonaModel::query()
            ->where('estado', 'ACTIVO')
            ->with(['rutas' => fn ($rutas) => $rutas->where('estado', 'ACTIVO')->orderBy('nombre')->select('id', 'zona_id', 'nombre')])
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => [
                'id' => (int) $zona->id,
                'nombre' => $zona->nombre,
                'rutas' => $zona->rutas->map(fn ($ruta): array => ['id' => (int) $ruta->id, 'nombre' => $ruta->nombre])->values()->all(),
            ])
            ->all();
    }

    /** @return list<array{id: int, nombre: string}> */
    private function zonas(): array
    {
        return ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all();
    }

    private function base(ProveedoresFiltro $filtro): Builder
    {
        $query = ProveedorModel::query()->from('proveedores as p')->toBase()
            ->leftJoin('zonas as z', 'z.id', '=', 'p.zona_id');

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $codigo = ltrim($filtro->busqueda, '#');

            $query->where(function (Builder $query) use ($busqueda, $codigo): void {
                $query->where('p.nombres', 'like', $busqueda)
                    ->orWhere('p.apellidos', 'like', $busqueda)
                    ->orWhere('p.dni', 'like', $busqueda)
                    ->orWhere('p.telefono', 'like', $busqueda);

                // El "código" del proveedor es su número de registro (columna #).
                if ($codigo !== '' && ctype_digit($codigo)) {
                    $query->orWhere('p.id', (int) $codigo);
                }
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
