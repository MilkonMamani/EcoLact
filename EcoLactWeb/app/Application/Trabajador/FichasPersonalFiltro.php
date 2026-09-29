<?php

namespace App\Application\Trabajador;

final readonly class FichasPersonalFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
    ) {}

    public static function resolver(?string $busqueda, ?string $estado): self
    {
        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: in_array($estado, ['ACTIVO', 'INACTIVO'], true) ? $estado : null,
        );
    }
}
