<?php

namespace App\Domain\Pago;

use DateTimeImmutable;

interface PeriodoLiquidacionRepository
{
    public function guardar(PeriodoLiquidacion $periodo): PeriodoLiquidacion;

    public function buscarPorId(int $id): ?PeriodoLiquidacion;

    public function buscarPorFechas(DateTimeImmutable $fechaInicio, DateTimeImmutable $fechaFin): ?PeriodoLiquidacion;
}
