<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Shared\Reloj;
use App\Application\Zona\ColorZona;
use App\Application\Zona\Contracts\ZonaPanelQuery;
use App\Application\Zona\Contracts\ZonaProveedoresQuery;
use App\Application\Zona\MomentoEntrega;
use App\Application\Zona\ProveedorAsignadoFila;
use App\Application\Zona\ZonaPanelData;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Zona\DelimitacionZona;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Panel lateral de Zonas (admin). Los proveedores salen de ZonaProveedoresQuery
 * (asignación vigente en una ruta de la zona, con su punto de recolección) y
 * sus entregas se agregan en UNA consulta agrupada; igual para acopiadores.
 * "Semana" es la semana EcoLact en curso (jueves a miércoles), sin entregas anuladas.
 */
final class EloquentZonaPanelQuery implements ZonaPanelQuery
{
    public function __construct(
        private readonly ZonaProveedoresQuery $zonaProveedores,
        private readonly Reloj $reloj,
    ) {}

    public function obtener(int $zonaId): ?ZonaPanelData
    {
        $zona = DB::table('zonas')->where('id', $zonaId)->first();

        if ($zona === null) {
            return null;
        }

        $hoy = $this->reloj->hoy();
        $semana = PeriodoPago::semanaQueContiene($hoy);
        $limitesSemana = [$semana->inicio->format('Y-m-d 00:00:00'), $semana->fin->format('Y-m-d 23:59:59')];

        return new ZonaPanelData(
            id: (int) $zona->id,
            nombre: $zona->nombre,
            descripcion: $zona->descripcion,
            estado: $zona->estado,
            creadaEl: $zona->created_at !== null ? CarbonImmutable::parse($zona->created_at)->format('d/m/Y') : null,
            litrosSemana: round((float) $this->entregasValidas()->where('e.zona_id', $zonaId)->whereBetween('e.fecha_hora', $limitesSemana)->sum('e.litros'), 2),
            rutas: DB::table('rutas')->where('zona_id', $zonaId)->orderBy('nombre')->get(['id', 'nombre', 'estado'])
                ->map(fn (object $ruta): array => ['id' => (int) $ruta->id, 'nombre' => $ruta->nombre, 'estado' => $ruta->estado])
                ->all(),
            proveedores: $this->proveedores($zonaId, $limitesSemana, $hoy),
            acopiadores: $this->acopiadores($zonaId, $limitesSemana),
            colorMapa: ColorZona::resolver($zona->color_mapa, (int) $zona->id),
            delimitacion: DelimitacionZona::desdeGeoJsonONull($zona->poligono_geojson),
        );
    }

    /**
     * @param  array{string, string}  $limitesSemana
     * @return list<array{fila: ProveedorAsignadoFila, telefono: ?string, ultimaEntrega: ?string, litrosSemana: float}>
     */
    private function proveedores(int $zonaId, array $limitesSemana, DateTimeImmutable $hoy): array
    {
        $filas = $this->zonaProveedores->deZona($zonaId);
        $ids = array_map(fn (ProveedorAsignadoFila $fila): int => $fila->proveedorId, $filas);

        $telefonos = DB::table('proveedores')->whereIn('id', $ids)->pluck('telefono', 'id');
        $entregas = $this->entregasValidas()
            ->whereIn('e.proveedor_id', $ids)
            ->groupBy('e.proveedor_id')
            ->select('e.proveedor_id')
            ->selectRaw('MAX(e.fecha_hora) as ultima')
            ->selectRaw('COALESCE(SUM(CASE WHEN e.fecha_hora BETWEEN ? AND ? THEN e.litros ELSE 0 END), 0) as litros_semana', $limitesSemana)
            ->get()
            ->keyBy('proveedor_id');

        return array_map(function (ProveedorAsignadoFila $fila) use ($telefonos, $entregas, $hoy): array {
            $resumen = $entregas->get($fila->proveedorId);

            return [
                'fila' => $fila,
                'telefono' => $telefonos->get($fila->proveedorId),
                'ultimaEntrega' => $resumen?->ultima !== null ? MomentoEntrega::etiqueta($resumen->ultima, $hoy) : null,
                'litrosSemana' => round((float) ($resumen->litros_semana ?? 0), 2),
            ];
        }, $filas);
    }

    /**
     * Acopiadores asignados a la zona con lo que entregaron en ella esta semana.
     *
     * @param  array{string, string}  $limitesSemana
     * @return list<array{id: int, nombre: string, dni: string, telefono: ?string, estado: string, entregasSemana: int, litrosSemana: float}>
     */
    private function acopiadores(int $zonaId, array $limitesSemana): array
    {
        $semana = $this->entregasValidas()
            ->where('e.zona_id', $zonaId)
            ->whereBetween('e.fecha_hora', $limitesSemana)
            ->groupBy('e.acopiador_id')
            ->select('e.acopiador_id')
            ->selectRaw('COUNT(*) as entregas')
            ->selectRaw('COALESCE(SUM(e.litros), 0) as litros')
            ->get()
            ->keyBy('acopiador_id');

        return DB::table('acopiadores')
            ->where('zona_id', $zonaId)
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'dni', 'telefono', 'estado'])
            ->map(fn (object $acopiador): array => [
                'id' => (int) $acopiador->id,
                'nombre' => trim($acopiador->nombres.' '.$acopiador->apellidos),
                'dni' => $acopiador->dni,
                'telefono' => $acopiador->telefono,
                'estado' => $acopiador->estado,
                'entregasSemana' => (int) ($semana->get($acopiador->id)->entregas ?? 0),
                'litrosSemana' => round((float) ($semana->get($acopiador->id)->litros ?? 0), 2),
            ])
            ->all();
    }

    private function entregasValidas(): Builder
    {
        return DB::table('entregas as e')->where('e.estado', '<>', EstadoEntrega::ANULADA->value);
    }
}
