<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\ObligacionesPagoFiltro;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ObligacionesPagoListadoQuery
{
    /**
     * @return array{
     *     kpis: array{pendientes: int, pagadosMes: int, totalPendiente: float, totalPagado: float},
     *     filas: LengthAwarePaginator
     * }
     */
    public function obtener(ObligacionesPagoFiltro $filtro, DateTimeImmutable $hoy): array;
}
