<?php

namespace App\Application\Zona;

use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;

final class ObtenerZona
{
    public function __construct(
        private readonly ZonaRepository $zonas,
    ) {}

    public function execute(int $zonaId): ?Zona
    {
        return $this->zonas->buscarPorId($zonaId);
    }
}
