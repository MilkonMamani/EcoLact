<?php

namespace App\Application\Pago;

/** Cálculo del pago de UNA entrega: litros pagables x (precio base + ajuste de calidad). */
final class DetalleEntregaCalculado
{
    public function __construct(
        public readonly int $entregaId,
        public readonly float $litrosEntregados,
        public readonly float $litrosPagables,
        public readonly ?string $resultado,
        public readonly float $precioBase,
        public readonly float $ajuste,
        public readonly float $precioFinal,
        public readonly float $subtotal,
    ) {}
}
