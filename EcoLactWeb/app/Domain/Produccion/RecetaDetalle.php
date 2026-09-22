<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Un ingrediente de una receta: cantidad de un insumo, expresada en una
 * unidad compatible con la unidad base de ese insumo.
 */
final class RecetaDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $insumoId,
        private readonly float $cantidad,
        private readonly string $unidad,
        private readonly ?string $observacion = null,
    ) {
        if ($cantidad <= 0.0) {
            throw new InvalidArgumentException('La cantidad de cada ingrediente debe ser mayor que cero.');
        }

        if (! UnidadMedida::esValida($unidad)) {
            throw new InvalidArgumentException("La unidad '{$unidad}' del ingrediente no es válida.");
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function insumoId(): int
    {
        return $this->insumoId;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function unidad(): string
    {
        return $this->unidad;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }
}
