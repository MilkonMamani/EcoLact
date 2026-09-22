<?php

namespace App\Application\Pago;

/** Resultado de confirmar la impresión y el pago en efectivo de varios proveedores. */
final class ResultadoPagoEnLote
{
    public function __construct(
        public readonly string $loteCodigo,
        public readonly int $pagados,
        public readonly float $total,
    ) {}
}
