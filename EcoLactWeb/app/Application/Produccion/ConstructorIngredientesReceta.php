<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\RecetaDetalle;
use App\Domain\Produccion\UnidadMedida;
use DomainException;

/**
 * Convierte y valida los ingredientes que llegan de un formulario de receta:
 * el insumo debe existir y estar activo, no puede repetirse, la cantidad debe
 * ser positiva y la unidad debe poder convertirse a la unidad base de ese
 * insumo (no se mezclan magnitudes: no hay "kg de leche" si la leche se
 * controla en litros).
 */
final class ConstructorIngredientesReceta
{
    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, cantidad: float|int|string, unidad: string, observacion?: string|null}>  $ingredientes
     * @return list<RecetaDetalle>
     */
    public function construir(array $ingredientes): array
    {
        if ($ingredientes === []) {
            throw new DomainException('La receta debe tener al menos un ingrediente.');
        }

        $detalles = [];
        $vistos = [];

        foreach ($ingredientes as $ingrediente) {
            $insumoId = (int) $ingrediente['insumo_id'];
            $cantidad = (float) $ingrediente['cantidad'];
            $unidad = (string) $ingrediente['unidad'];

            $insumo = $this->insumos->buscarPorId($insumoId)
                ?? throw new DomainException('Uno de los ingredientes seleccionados ya no existe.');

            if ($insumo->estado() !== 'ACTIVO') {
                throw new DomainException("El insumo {$insumo->nombre()} está inactivo y no se puede usar en una receta.");
            }

            if (isset($vistos[$insumoId])) {
                throw new DomainException("El insumo {$insumo->nombre()} está repetido: use una sola línea por ingrediente.");
            }

            $vistos[$insumoId] = true;

            if ($cantidad <= 0.0) {
                throw new DomainException("La cantidad de {$insumo->nombre()} debe ser mayor que cero.");
            }

            if (! UnidadMedida::esValida($unidad) || ! UnidadMedida::sonCompatibles($unidad, $insumo->unidadBase())) {
                throw new DomainException("{$insumo->nombre()} se controla en {$insumo->unidadBase()}; la unidad {$unidad} no es compatible.");
            }

            $observacion = isset($ingrediente['observacion']) ? trim((string) $ingrediente['observacion']) : '';

            $detalles[] = new RecetaDetalle(id: null, insumoId: $insumoId, cantidad: $cantidad, unidad: $unidad, observacion: $observacion !== '' ? $observacion : null);
        }

        return $detalles;
    }
}
