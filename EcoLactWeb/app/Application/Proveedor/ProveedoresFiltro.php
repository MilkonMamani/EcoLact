<?php

namespace App\Application\Proveedor;

final readonly class ProveedoresFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
        public ?int $zonaId,
        public ?string $seguimiento,
    ) {}

    public static function resolver(
        ?string $busqueda,
        ?string $estado,
        ?int $zonaId,
        ?string $seguimiento,
    ): self {
        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
            zonaId: $zonaId,
            seguimiento: in_array($seguimiento, ['con_seguimiento', 'sin_seguimiento'], true) ? $seguimiento : null,
        );
    }
}
