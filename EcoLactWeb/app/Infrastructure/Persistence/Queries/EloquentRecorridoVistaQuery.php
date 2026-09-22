<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Recorrido\Contracts\RecorridoVistaQuery;
use App\Application\Recorrido\ParadaVista;
use App\Application\Recorrido\RecorridoVista;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\RecorridoAcopioModel;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use DomainException;

final class EloquentRecorridoVistaQuery implements RecorridoVistaQuery
{
    public function obtener(int $recorridoId): RecorridoVista
    {
        $recorrido = RecorridoAcopioModel::with('ruta.zona')->find($recorridoId);

        if ($recorrido === null) {
            throw new DomainException('El recorrido indicado no existe.');
        }

        $detalles = RecorridoDetalleModel::with('proveedor')
            ->where('recorrido_id', $recorridoId)
            ->orderBy('orden_visita')
            ->get();

        $litrosPorParada = EntregaModel::query()
            ->whereIn('recorrido_detalle_id', $detalles->pluck('id'))
            ->where('estado', '!=', 'ANULADA')
            ->selectRaw('recorrido_detalle_id, SUM(litros) as total')
            ->groupBy('recorrido_detalle_id')
            ->pluck('total', 'recorrido_detalle_id');

        $paradas = $detalles->map(fn (RecorridoDetalleModel $d): ParadaVista => new ParadaVista(
            id: $d->id,
            proveedorId: $d->proveedor_id,
            proveedorNombre: trim($d->proveedor->nombres.' '.$d->proveedor->apellidos),
            proveedorDni: $d->proveedor->dni,
            ordenVisita: $d->orden_visita,
            latitud: $d->latitud !== null ? (float) $d->latitud : null,
            longitud: $d->longitud !== null ? (float) $d->longitud : null,
            referenciaUbicacion: $d->referencia_ubicacion,
            estado: $d->estado,
            observacion: $d->observacion,
            litrosRegistrados: (float) ($litrosPorParada[$d->id] ?? 0.0),
        ))->values()->all();

        $progreso = [
            'recolectados' => $detalles->where('estado', 'RECOLECTADO')->count(),
            'pendientes' => $detalles->where('estado', 'PENDIENTE')->count(),
            'no_entrego' => $detalles->where('estado', 'NO_ENTREGO')->count(),
            'litros_validos' => round((float) $litrosPorParada->sum(), 2),
        ];

        return new RecorridoVista(
            id: $recorrido->id,
            rutaId: $recorrido->ruta_id,
            rutaNombre: $recorrido->ruta->nombre,
            zonaNombre: $recorrido->ruta->zona->nombre,
            estado: $recorrido->estado,
            fechaOperativa: $recorrido->fecha_operativa->toDateString(),
            paradas: $paradas,
            progreso: $progreso,
        );
    }
}
