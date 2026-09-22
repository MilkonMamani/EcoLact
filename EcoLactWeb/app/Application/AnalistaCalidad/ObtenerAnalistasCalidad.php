<?php

namespace App\Application\AnalistaCalidad;

use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;

final class ObtenerAnalistasCalidad
{
    public function __construct(
        private readonly AnalistaCalidadRepository $analistas,
    ) {}

    /**
     * @return AnalistaCalidad[]
     */
    public function execute(): array
    {
        return $this->analistas->listar();
    }
}
