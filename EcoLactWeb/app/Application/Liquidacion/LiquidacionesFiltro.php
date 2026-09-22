<?php

namespace App\Application\Liquidacion;

final readonly class LiquidacionesFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
        public ?string $incidencia,
        public ?int $zonaId,
        public ?int $periodoId,
    ) {}

    public static function resolver(
        ?string $busqueda,
        ?string $estado,
        ?string $incidencia,
        ?int $zonaId,
        ?int $periodoId = null,
    ): self {
        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
            incidencia: in_array($incidencia, ['con_incidencia', 'sin_incidencia'], true) ? $incidencia : null,
            zonaId: $zonaId,
            periodoId: $periodoId,
        );
    }
}
