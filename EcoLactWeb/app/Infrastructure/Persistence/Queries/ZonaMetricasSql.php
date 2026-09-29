<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Zona\ZonasFiltro;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SQL compartido por la tabla "Resumen de zonas" (EloquentZonasListadoQuery) y el mapa general
 * (EloquentZonasMapaQuery), para que ambos muestren exactamente los mismos números.
 */
final class ZonaMetricasSql
{
    /** Zonas (alias z) que pasan la búsqueda por nombre/descripción y el filtro de estado. */
    public static function filtradas(ZonasFiltro $filtro): Builder
    {
        $query = ZonaModel::query()->from('zonas as z')->toBase();

        if ($filtro->busqueda !== null) {
            $patron = '%'.$filtro->busqueda.'%';
            $query->where(fn (Builder $grupo) => $grupo->where('z.nombre', 'like', $patron)->orWhere('z.descripcion', 'like', $patron));
        }

        if ($filtro->estado !== null) {
            $query->where('z.estado', $filtro->estado);
        }

        return $query;
    }

    /**
     * Cada agregado es una subconsulta correlacionada: una sola consulta para todas las zonas,
     * sin N+1. proveedores_count = TODOS los proveedores con asignación vigente en una ruta de la
     * zona (tengan o no coordenadas); litros de la semana EcoLact en curso (jueves a miércoles) y
     * última entrega, ambos sin entregas anuladas.
     */
    public static function conMetricas(Builder $query, DateTimeImmutable $hoy): Builder
    {
        $entregasValidas = fn (): Builder => DB::table('entregas as e')
            ->whereColumn('e.zona_id', 'z.id')
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value);

        return $query
            ->select('z.id', 'z.nombre', 'z.descripcion', 'z.estado')
            ->selectSub(DB::table('ruta_detalles as rd')->join('rutas as r', 'r.id', '=', 'rd.ruta_id')->whereColumn('r.zona_id', 'z.id')->selectRaw('COUNT(*)'), 'proveedores_count')
            ->selectSub(DB::table('acopiadores as a')->whereColumn('a.zona_id', 'z.id')->selectRaw('COUNT(*)'), 'acopiadores_count')
            ->selectSub($entregasValidas()->whereBetween('e.fecha_hora', self::limitesSemana($hoy))->selectRaw('COALESCE(SUM(e.litros), 0)'), 'litros_semana')
            ->selectSub($entregasValidas()->selectRaw('MAX(e.fecha_hora)'), 'ultima_entrega');
    }

    /** @return array{string, string} */
    public static function limitesSemana(DateTimeImmutable $hoy): array
    {
        $semana = PeriodoPago::semanaQueContiene($hoy);

        return [$semana->inicio->format('Y-m-d 00:00:00'), $semana->fin->format('Y-m-d 23:59:59')];
    }
}
