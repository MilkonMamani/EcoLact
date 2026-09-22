<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Fotografía histórica del consumo de un insumo en un lote: qué calculó la
 * receta (cantidadRequerida) y qué se descontó realmente (cantidadUsada),
 * siempre en la unidad base del insumo. No cambia aunque la receta o el
 * insumo se modifiquen después.
 */
final class LoteProduccionInsumo
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $loteProduccionId,
        private readonly int $insumoId,
        private readonly float $cantidadUsada,
        private readonly ?float $cantidadRequerida = null,
        private readonly ?string $unidadBase = null,
    ) {
        if ($cantidadUsada <= 0.0) {
            throw new InvalidArgumentException('La cantidad usada de un insumo debe ser mayor a cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function loteProduccionId(): int
    {
        return $this->loteProduccionId;
    }

    public function insumoId(): int
    {
        return $this->insumoId;
    }

    public function cantidadUsada(): float
    {
        return $this->cantidadUsada;
    }

    public function cantidadRequerida(): float
    {
        return $this->cantidadRequerida ?? $this->cantidadUsada;
    }

    public function unidadBase(): ?string
    {
        return $this->unidadBase;
    }
}
