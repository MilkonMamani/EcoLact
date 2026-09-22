<?php

namespace App\Application\Ruta;

final readonly class RutaDetalleFila
{
    public function __construct(
        public int $id,
        public int $proveedorId,
        public string $proveedorNombre,
        public string $proveedorDni,
        public int $ordenVisita,
        public ?float $latitud,
        public ?float $longitud,
        public ?string $referenciaUbicacion,
    ) {}
}
