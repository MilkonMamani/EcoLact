<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Application\Produccion\LoteProduccionDetalleData;

/**
 * Representación JSON del detalle de un lote de producción, compartida por
 * las APIs de Planta y Admin. La leche utilizada se deriva del consumo real
 * del insumo LECHE registrado en el lote.
 */
trait SerializaLoteProduccion
{
    /**
     * @return array<string, mixed>
     */
    private function serializarLote(LoteProduccionDetalleData $detalle): array
    {
        return [
            'id' => $detalle->lote->id(),
            'codigo' => $detalle->lote->codigo(),
            'estado' => $detalle->lote->estado(),
            'fecha_hora_inicio' => $detalle->lote->fechaHoraInicio()->format(DATE_ATOM),
            'fecha_hora_fin' => $detalle->lote->fechaHoraFin()?->format(DATE_ATOM),
            'litros_leche_utilizados' => $detalle->lecheUtilizada,
            'observacion' => $detalle->lote->observacion(),
            'productos' => array_map(static fn (array $producto): array => [
                'detalle_id' => $producto['detalleId'],
                'producto_id' => $producto['productoId'],
                'producto_nombre' => $producto['productoNombre'],
                'unidad_medida' => $producto['unidadMedida'],
                'cantidad_planificada' => $producto['cantidadPlanificada'],
                'cantidad_obtenida' => $producto['cantidadObtenida'],
                'receta_id' => $producto['recetaId'],
                'receta_version' => $producto['recetaVersion'],
            ], $detalle->productos),
            'insumos_utilizados' => array_map(static fn (array $insumo): array => [
                'insumo_id' => $insumo['insumoId'],
                'insumo_nombre' => $insumo['insumoNombre'],
                'unidad_base' => $insumo['unidadBase'],
                'cantidad_requerida' => $insumo['cantidadRequerida'],
                'cantidad_usada' => $insumo['cantidadUsada'],
            ], $detalle->insumosUtilizados),
        ];
    }
}
