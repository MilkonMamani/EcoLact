<?php

namespace App\Application\Zona;

final readonly class ZonasFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
    ) {}

    public static function resolver(?string $busqueda, ?string $estado): self
    {
        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
        );
    }
}
