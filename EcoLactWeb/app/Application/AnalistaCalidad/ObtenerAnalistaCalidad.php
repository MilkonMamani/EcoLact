<?php

namespace App\Application\AnalistaCalidad;

use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;

final class ObtenerAnalistaCalidad
{
    public function __construct(
        private readonly AnalistaCalidadRepository $analistas,
    ) {}

    public function execute(int $analistaId): ?AnalistaCalidad
    {
        return $this->analistas->buscarPorId($analistaId);
    }
}
