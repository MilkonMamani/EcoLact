<?php

namespace App\Application\Recorrido;

final readonly class ParadaVista
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
        public string $estado,
        public ?string $observacion,
        public float $litrosRegistrados,
    ) {}
}
