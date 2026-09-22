<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ReglaCalidad;
use App\Domain\Calidad\ReglaCalidadRepository;

final class ObtenerReglasCalidadPorParametro
{
    public function __construct(
        private readonly ReglaCalidadRepository $reglasCalidad,
    ) {}

    /**
     * @return ReglaCalidad[]
     */
    public function execute(int $parametroId): array
    {
        return $this->reglasCalidad->listarPorParametro($parametroId);
    }
}
