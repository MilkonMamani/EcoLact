<?php

namespace App\Domain\Pago;

interface LiquidacionDetalleRepository
{
    public function guardar(LiquidacionDetalle $detalle): LiquidacionDetalle;

    /**
     * @return LiquidacionDetalle[]
     */
    public function listarPorLiquidacion(int $liquidacionId): array;
}
