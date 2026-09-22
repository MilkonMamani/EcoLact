<?php

namespace App\Application\EncargadoPlanta;

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;

final class ObtenerEncargadoPlanta
{
    public function __construct(
        private readonly EncargadoPlantaRepository $encargados,
    ) {}

    public function execute(int $encargadoId): ?EncargadoPlanta
    {
        return $this->encargados->buscarPorId($encargadoId);
    }
}
