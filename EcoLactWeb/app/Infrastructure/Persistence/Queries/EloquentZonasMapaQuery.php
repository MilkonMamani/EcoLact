<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Shared\Reloj;
use App\Application\Zona\Contracts\ZonasMapaQuery;
use App\Application\Zona\ZonasFiltro;
use App\Domain\Shared\Enums\EstadoEntrega;
use Illuminate\Support\Facades\DB;

/**
 * MySQL → mapa general de Zonas. Tres consultas fijas sin importar cuántas zonas o
 * proveedores haya (zonas con métricas, puntos de recolección, entregas agrupadas por
 * proveedor): sin N+1. Las métricas son las mismas de la tabla (ZonaMetricasSql).
 */
final class EloquentZonasMapaQuery implements ZonasMapaQuery
{
    public function __construct(private readonly Reloj $reloj) {}

    public function zonas(ZonasFiltro $filtro): array
    {
        return ZonaMetricasSql::conMetricas(ZonaMetricasSql::filtradas($filtro), $this->reloj->hoy())
            ->addSelect('z.color_mapa', 'z.poligono_geojson')
            ->orderBy('z.nombre')
            ->orderBy('z.id')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'estado' => $fila->estado,
                'colorMapa' => $fila->color_mapa,
                'poligonoGeoJson' => $fila->poligono_geojson,
                'proveedores' => (int) $fila->proveedores_count,
                'acopiadores' => (int) $fila->acopiadores_count,
                'litrosSemana' => round((float) $fila->litros_semana, 2),
                'ultimaEntrega' => $fila->ultima_entrega,
            ])
            ->all();
    }

    public function proveedoresConCoordenadas(array $zonaIds): array
    {
        if ($zonaIds === []) {
            return [];
        }

        $puntos = DB::table('ruta_detalles as rd')
            ->join('rutas as r', 'r.id', '=', 'rd.ruta_id')
            ->join('proveedores as p', 'p.id', '=', 'rd.proveedor_id')
            ->whereIn('r.zona_id', $zonaIds)
            ->whereNotNull('rd.latitud')
            ->whereNotNull('rd.longitud')
            ->orderBy('p.nombres')
            ->orderBy('p.apellidos')
            ->get(['p.id', 'p.nombres', 'p.apellidos', 'p.dni', 'rd.latitud', 'rd.longitud', 'r.zona_id']);

        $entregas = DB::table('entregas as e')
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value)
            ->whereIn('e.proveedor_id', $puntos->pluck('id')->all())
            ->groupBy('e.proveedor_id')
            ->select('e.proveedor_id')
            ->selectRaw('MAX(e.fecha_hora) as ultima')
            ->selectRaw('COALESCE(SUM(CASE WHEN e.fecha_hora BETWEEN ? AND ? THEN e.litros ELSE 0 END), 0) as litros_semana', ZonaMetricasSql::limitesSemana($this->reloj->hoy()))
            ->get()
            ->keyBy('proveedor_id');

        return $puntos->map(fn (object $punto): array => [
            'id' => (int) $punto->id,
            'nombre' => trim($punto->nombres.' '.$punto->apellidos),
            'dni' => $punto->dni,
            'zonaId' => (int) $punto->zona_id,
            'lat' => (float) $punto->latitud,
            'lng' => (float) $punto->longitud,
            'ultimaEntrega' => $entregas->get($punto->id)?->ultima,
            'litrosSemana' => round((float) ($entregas->get($punto->id)->litros_semana ?? 0), 2),
        ])->values()->all();
    }
}
