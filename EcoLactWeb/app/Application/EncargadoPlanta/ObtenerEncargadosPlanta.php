<?php

namespace App\Application\EncargadoPlanta;

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;

final class ObtenerEncargadosPlanta
{
    public function __construct(
        private readonly EncargadoPlantaRepository $encargados,
    ) {}

    /**
     * @return EncargadoPlanta[]
     */
    public function execute(): array
    {
        return $this->encargados->listar();
    }
}
