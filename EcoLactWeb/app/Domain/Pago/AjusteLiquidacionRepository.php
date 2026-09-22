<?php

namespace App\Domain\Pago;

interface AjusteLiquidacionRepository
{
    public function guardar(AjusteLiquidacion $ajuste): AjusteLiquidacion;

    /**
     * @return AjusteLiquidacion[]
     */
    public function listarPorLiquidacion(int $liquidacionId): array;
}
