<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Ruta\Contracts\RutaDetallesListadoQuery;
use App\Application\Ruta\RutaDetalleFila;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;

final class EloquentRutaDetallesListadoQuery implements RutaDetallesListadoQuery
{
    /**
     * @return RutaDetalleFila[]
     */
    public function obtener(int $rutaId): array
    {
        return RutaDetalleModel::query()
            ->where('ruta_id', $rutaId)
            ->join('proveedores', 'proveedores.id', '=', 'ruta_detalles.proveedor_id')
            ->orderBy('ruta_detalles.orden_visita')
            ->select([
                'ruta_detalles.id as id',
                'proveedores.id as proveedor_id',
                'proveedores.nombres as proveedor_nombres',
                'proveedores.apellidos as proveedor_apellidos',
                'proveedores.dni as proveedor_dni',
                'ruta_detalles.orden_visita as orden_visita',
                'ruta_detalles.latitud as latitud',
                'ruta_detalles.longitud as longitud',
                'ruta_detalles.referencia_ubicacion as referencia_ubicacion',
            ])
            ->get()
            ->map(fn (object $fila): RutaDetalleFila => new RutaDetalleFila(
                id: (int) $fila->id,
                proveedorId: (int) $fila->proveedor_id,
                proveedorNombre: trim($fila->proveedor_nombres.' '.$fila->proveedor_apellidos),
                proveedorDni: $fila->proveedor_dni,
                ordenVisita: (int) $fila->orden_visita,
                latitud: $fila->latitud !== null ? (float) $fila->latitud : null,
                longitud: $fila->longitud !== null ? (float) $fila->longitud : null,
                referenciaUbicacion: $fila->referencia_ubicacion,
            ))
            ->all();
    }
}
