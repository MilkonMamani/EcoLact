<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\LiquidacionRepository;

final class ObtenerLiquidacion
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
    ) {}

    public function execute(int $liquidacionId): ?Liquidacion
    {
        return $this->liquidaciones->buscarPorId($liquidacionId);
    }
}
