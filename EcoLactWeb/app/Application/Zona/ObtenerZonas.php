<?php

namespace App\Application\Zona;

use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;

final class ObtenerZonas
{
    public function __construct(
        private readonly ZonaRepository $zonas,
    ) {}

    /**
     * @return Zona[]
     */
    public function execute(): array
    {
        return $this->zonas->listar();
    }
}
