<?php

namespace App\Application\Ruta;

use App\Domain\Ruta\Ruta;
use App\Domain\Ruta\RutaRepository;

final class ObtenerRutasPorZona
{
    public function __construct(
        private readonly RutaRepository $rutas,
    ) {}

    /**
     * @return Ruta[]
     */
    public function execute(int $zonaId): array
    {
        return $this->rutas->listarPorZona($zonaId);
    }
}
