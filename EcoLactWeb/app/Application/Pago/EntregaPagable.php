<?php

namespace App\Application\Pago;

use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;

/** Entrega válida (no anulada) que todavía no forma parte de un pago ya realizado. */
final class EntregaPagable
{
    public function __construct(
        public readonly int $entregaId,
        public readonly int $proveedorId,
        public readonly string $proveedorNombre,
        public readonly DateTimeImmutable $fechaHora,
        public readonly float $litros,
        /** Resultado del análisis CONFIRMADO; nulo si no hay análisis o sigue en borrador. */
        public readonly ?ResultadoCalidad $resultado,
    ) {}
}
