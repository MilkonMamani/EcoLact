<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\ReglaPagoCalidad;
use App\Domain\Pago\ReglaPagoCalidadRepository;

final class ObtenerReglasPagoCalidad
{
    public function __construct(
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
    ) {}

    /**
     * @return ReglaPagoCalidad[]
     */
    public function execute(): array
    {
        return $this->reglasPagoCalidad->listar();
    }
}
