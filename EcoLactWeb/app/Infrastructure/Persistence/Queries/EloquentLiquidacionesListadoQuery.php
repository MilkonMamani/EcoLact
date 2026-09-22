<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Liquidacion\Contracts\LiquidacionesListadoQuery;
use App\Application\Liquidacion\LiquidacionesFiltro;
use App\Application\Liquidacion\LiquidacionesListadoData;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Database\Query\Builder;

final class EloquentLiquidacionesListadoQuery implements LiquidacionesListadoQuery
{
    public function obtener(LiquidacionesFiltro $filtro): LiquidacionesListadoData
    {
        $resumen = $this->base($filtro)
            ->selectRaw('COUNT(DISTINCT l.proveedor_id) as proveedores')
            ->selectRaw('COALESCE(SUM(l.total_litros_entregados), 0) as litros')
            ->selectRaw('COALESCE(SUM(l.total_pagar), 0) as total_pagar')
            ->selectRaw("SUM(CASE WHEN l.estado IN ('PENDIENTE', 'PARCIALMENTE_PAGADA') THEN 1 ELSE 0 END) as pendientes")
            ->first();

        $kpis = [
            'proveedoresLiquidados' => (int) $resumen->proveedores,
            'litrosEntregados' => round((float) $resumen->litros, 2),
            'totalPagar' => round((float) $resumen->total_pagar, 2),
            'pendientesPago' => (int) $resumen->pendientes,
        ];

        $filas = $this->base($filtro)
            ->select(
                'l.id',
                'p.nombres',
                'p.apellidos',
                'p.dni',
                'per.fecha_inicio',
                'per.fecha_fin',
                'l.total_litros_entregados',
                'l.total_litros_pagables',
                'l.precio_litro_aplicado',
                'l.tuvo_incidencia',
                'l.total_pagar',
                'l.estado',
            )
            ->orderByDesc('per.fecha_inicio')
            ->orderBy('p.nombres')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
                'proveedorDni' => $fila->dni,
                'periodoDesde' => $fila->fecha_inicio,
                'periodoHasta' => $fila->fecha_fin,
                'litrosEntregados' => (float) $fila->total_litros_entregados,
                'litrosPagables' => (float) $fila->total_litros_pagables,
                'precioAplicado' => (float) $fila->precio_litro_aplicado,
                'tuvoIncidencia' => (bool) $fila->tuvo_incidencia,
                'totalPagar' => (float) $fila->total_pagar,
                'estado' => $fila->estado,
            ])
            ->all();

        $zonas = ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all();

        // Solo periodos que ya tienen al menos una liquidación generada (no
        // todos los periodos posibles): son los únicos sobre los que tiene
        // sentido "imprimir los sobres de este periodo".
        $periodos = PeriodoLiquidacionModel::query()
            ->whereHas('liquidaciones')
            ->orderByDesc('fecha_inicio')
            ->get(['id', 'fecha_inicio', 'fecha_fin'])
            ->map(fn (PeriodoLiquidacionModel $periodo): array => [
                'id' => (int) $periodo->id,
                'label' => $periodo->fecha_inicio->format('d/m/Y').' – '.$periodo->fecha_fin->format('d/m/Y'),
            ])->all();

        return new LiquidacionesListadoData(kpis: $kpis, zonas: $zonas, periodos: $periodos, filas: $filas);
    }

    private function base(LiquidacionesFiltro $filtro): Builder
    {
        $query = LiquidacionModel::query()->from('liquidaciones as l')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'l.proveedor_id')
            ->join('periodos_liquidacion as per', 'per.id', '=', 'l.periodo_id');

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $query->where(function (Builder $query) use ($busqueda, $filtro): void {
                $query->where('p.nombres', 'like', $busqueda)
                    ->orWhere('p.apellidos', 'like', $busqueda)
                    ->orWhere('p.dni', 'like', $busqueda)
                    ->orWhere('l.id', $filtro->busqueda);
            });
        }

        if ($filtro->estado !== null) {
            $query->where('l.estado', $filtro->estado);
        }

        if ($filtro->incidencia === 'con_incidencia') {
            $query->where('l.tuvo_incidencia', true);
        } elseif ($filtro->incidencia === 'sin_incidencia') {
            $query->where('l.tuvo_incidencia', false);
        }

        if ($filtro->zonaId !== null) {
            $query->where('p.zona_id', $filtro->zonaId);
        }

        if ($filtro->periodoId !== null) {
            $query->where('l.periodo_id', $filtro->periodoId);
        }

        return $query;
    }
}
