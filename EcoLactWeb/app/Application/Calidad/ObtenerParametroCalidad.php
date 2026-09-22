<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ParametroCalidad;
use App\Domain\Calidad\ParametroCalidadRepository;

final class ObtenerParametroCalidad
{
    public function __construct(
        private readonly ParametroCalidadRepository $parametros,
    ) {}

    public function execute(int $parametroId): ?ParametroCalidad
    {
        return $this->parametros->buscarPorId($parametroId);
    }
}
