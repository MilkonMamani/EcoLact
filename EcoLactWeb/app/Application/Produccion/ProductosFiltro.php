<?php

namespace App\Application\Produccion;

final readonly class ProductosFiltro
{
    public const DISPONIBLE = 'DISPONIBLE';

    public const SIN_STOCK = 'SIN_STOCK';

    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
    ) {}

    /**
     * @param  string|null  $estado  DISPONIBLE (stock > 0) o SIN_STOCK (stock = 0)
     */
    public static function resolver(?string $busqueda, ?string $estado): self
    {
        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: in_array($estado, [self::DISPONIBLE, self::SIN_STOCK], true) ? $estado : null,
        );
    }
}
