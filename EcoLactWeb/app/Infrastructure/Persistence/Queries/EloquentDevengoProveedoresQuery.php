<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\DevengoProveedoresQuery;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/** Una sola consulta, sumas DECIMAL en SQL redondeadas una vez. */
final class EloquentDevengoProveedoresQuery implements DevengoProveedoresQuery
{
    public function resumen(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $zonaApp = new DateTimeZone(config('app.timezone'));
        $pendientes = "'".EstadoMuestreo::SELECCIONADA->value."', '".EstadoMuestreo::EN_ANALISIS->value."'";
        $noPagable = "'".EstadoObligacionPago::NO_PAGABLE->value."'";
        $muestraPendiente = "EXISTS (SELECT 1 FROM muestreos_calidad m WHERE m.entrega_id = e.id AND m.estado IN ({$pendientes}))";
        $semanaConMuestraPendiente = 'EXISTS (SELECT 1 FROM obligacion_pago_detalles d2 JOIN muestreos_calidad m2 ON m2.entrega_id = d2.entrega_id'
            ." WHERE d2.obligacion_pago_id = o.id AND m2.estado IN ({$pendientes}))";

        $fila = DB::table('entregas as e')
            ->leftJoin('obligacion_pago_detalles as d', 'd.entrega_id', '=', 'e.id')
            ->leftJoin('obligaciones_pago as o', 'o.id', '=', 'd.obligacion_pago_id')
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value)
            ->where('e.fecha_hora', '>=', $desde->setTimezone($zonaApp)->format('Y-m-d H:i:s'))
            ->where('e.fecha_hora', '<', $hasta->setTimezone($zonaApp)->format('Y-m-d H:i:s'))
            ->selectRaw('COUNT(*) as entregas, COALESCE(SUM(e.litros), 0) as recibidos')
            ->selectRaw("COALESCE(SUM(CASE WHEN {$muestraPendiente} THEN e.litros ELSE 0 END), 0) as en_analisis")
            ->selectRaw("COALESCE(SUM(CASE WHEN o.estado <> {$noPagable} THEN d.litros_pagables ELSE 0 END), 0) as valorizados")
            ->selectRaw("COALESCE(SUM(CASE WHEN d.id IS NOT NULL AND (o.estado = {$noPagable} OR d.litros_pagables = 0) THEN e.litros ELSE 0 END), 0) as no_pagables")
            ->selectRaw('COALESCE(SUM(CASE WHEN d.id IS NULL THEN e.litros ELSE 0 END), 0) as sin_valorizar')
            ->selectRaw("COALESCE(SUM(CASE WHEN o.estado <> {$noPagable} THEN d.subtotal ELSE 0 END), 0) as devengado")
            ->selectRaw("COALESCE(SUM(CASE WHEN o.estado = ? AND {$semanaConMuestraPendiente} THEN d.subtotal ELSE 0 END), 0) as sujeto", [EstadoObligacionPago::PENDIENTE->value])
            ->first();

        return [
            'entregas' => (int) $fila->entregas,
            'litrosRecibidos' => round((float) $fila->recibidos, 2),
            'litrosEnAnalisis' => round((float) $fila->en_analisis, 2),
            'litrosValorizados' => round((float) $fila->valorizados, 2),
            'litrosNoPagables' => round((float) $fila->no_pagables, 2),
            'litrosSinValorizar' => round((float) $fila->sin_valorizar, 2),
            'devengado' => round((float) $fila->devengado, 2),
            'sujetoACalidad' => round((float) $fila->sujeto, 2),
        ];
    }
}
