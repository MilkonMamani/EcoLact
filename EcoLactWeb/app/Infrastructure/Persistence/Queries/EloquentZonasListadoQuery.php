<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Shared\Reloj;
use App\Application\Zona\Contracts\ZonasListadoQuery;
use App\Application\Zona\MomentoEntrega;
use App\Application\Zona\ZonasFiltro;
use App\Application\Zona\ZonasListadoData;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentZonasListadoQuery implements ZonasListadoQuery
{
    public function __construct(private readonly Reloj $reloj) {}

    public function obtener(ZonasFiltro $filtro): ZonasListadoData
    {
        $kpis = [
            'activas' => (int) ZonaModel::where('estado', 'ACTIVO')->count(),
            'inactivas' => (int) ZonaModel::where('estado', 'INACTIVO')->count(),
            'proveedoresAsignados' => (int) ProveedorModel::whereNotNull('zona_id')->count(),
            'acopiadoresAsignados' => (int) AcopiadorModel::count(),
        ];

        $filas = ZonaMetricasSql::filtradas($filtro)
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

    /**
     * Los proveedores se cuentan por su asignación vigente en una ruta de la
     * zona (la misma fuente que la pestaña Proveedores y que "Proveedores sin
     * zona"). Ver ZonaMetricasSql::conMetricas().
     */
    public function paginar(ZonasFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): LengthAwarePaginator
    {
        $hoy = $this->reloj->hoy();

        return ZonaMetricasSql::conMetricas(ZonaMetricasSql::filtradas($filtro), $hoy)
            ->orderBy('z.nombre')
            ->orderBy('z.id')
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString()
            ->through(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'descripcion' => $fila->descripcion,
                'proveedores' => (int) $fila->proveedores_count,
                'acopiadores' => (int) $fila->acopiadores_count,
                'litrosSemana' => round((float) $fila->litros_semana, 2),
                'ultimaEntrega' => $fila->ultima_entrega !== null ? MomentoEntrega::etiqueta($fila->ultima_entrega, $hoy) : null,
                'estado' => $fila->estado,
            ]);
    }
}
