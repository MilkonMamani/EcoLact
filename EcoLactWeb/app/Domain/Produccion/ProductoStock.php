<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Saldo de un producto terminado, calculado a partir de sus movimientos
 * (producto_movimientos). No se persiste: es un valor de lectura.
 */
final class ProductoStock
{
    public function __construct(
        private readonly int $productoId,
        private readonly float $cantidadActual,
    ) {
        if ($cantidadActual < 0.0) {
            throw new InvalidArgumentException('El stock de un producto no puede ser negativo.');
        }
    }

    public function productoId(): int
    {
        return $this->productoId;
    }

    public function cantidadActual(): float
    {
        return $this->cantidadActual;
    }
}
