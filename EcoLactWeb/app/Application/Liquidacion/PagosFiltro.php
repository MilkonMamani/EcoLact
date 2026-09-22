<?php

namespace App\Application\Liquidacion;

/**
 * El esquema no distingue "estado de pago" de "estado de liquidación" como
 * dos columnas separadas: liquidaciones.estado (PENDIENTE/
 * PARCIALMENTE_PAGADA/PAGADA/ANULADA) ya es, en la práctica, el estado de
 * la obligación de pago. Por eso hay un único filtro de estado, no dos.
 */
final readonly class PagosFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
        public ?int $zonaId,
    ) {}

    public static function resolver(
        ?string $busqueda,
        ?string $estado,
        ?int $zonaId,
    ): self {
        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
            zonaId: $zonaId,
        );
    }
}
