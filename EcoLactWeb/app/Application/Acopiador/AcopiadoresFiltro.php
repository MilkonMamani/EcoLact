<?php

namespace App\Application\Acopiador;

final readonly class AcopiadoresFiltro
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
