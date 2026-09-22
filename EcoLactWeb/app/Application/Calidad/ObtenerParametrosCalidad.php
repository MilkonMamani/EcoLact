<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ParametroCalidad;
use App\Domain\Calidad\ParametroCalidadRepository;

final class ObtenerParametrosCalidad
{
    public function __construct(
        private readonly ParametroCalidadRepository $parametros,
    ) {}

    /**
     * @return ParametroCalidad[]
     */
    public function execute(): array
    {
        return $this->parametros->listar();
    }
}
