<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ReglaCalidad;
use App\Domain\Calidad\ReglaCalidadRepository;

final class ObtenerReglasCalidad
{
    public function __construct(
        private readonly ReglaCalidadRepository $reglasCalidad,
    ) {}

    /**
     * @return ReglaCalidad[]
     */
    public function execute(): array
    {
        return $this->reglasCalidad->listar();
    }
}
