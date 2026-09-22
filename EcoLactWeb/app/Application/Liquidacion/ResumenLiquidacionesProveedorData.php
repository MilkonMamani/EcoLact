<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\Liquidacion;

final readonly class ResumenLiquidacionesProveedorData
{
    public function __construct(
        public ?Liquidacion $ultimaLiquidacion,
        public float $porCobrar,
        public float $pagado,
        public int $totalLiquidaciones,
    ) {}
}
