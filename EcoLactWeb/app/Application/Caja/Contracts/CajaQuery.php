<?php

namespace App\Application\Caja\Contracts;

use App\Application\Caja\CajaFiltro;
use App\Application\Caja\CajaResumen;
use App\Application\Caja\MovimientoCajaFila;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CajaQuery
{
    /** Saldo total y totales del día `$hoy`, con consultas agregadas. */
    public function resumen(DateTimeImmutable $hoy): CajaResumen;

    /**
     * Historial, del más reciente al más antiguo.
     *
     * @return LengthAwarePaginator<int, MovimientoCajaFila>
     */
    public function movimientos(CajaFiltro $filtro): LengthAwarePaginator;

    public function buscar(int $id): ?MovimientoCajaFila;
}
