<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\ComprobantePagoTrabajador;
use App\Application\Pago\TrabajadoresPagoFiltro;
use App\Application\Pago\TrabajadorPagoFila;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TrabajadoresPagoListadoQuery
{
    /**
     * @return LengthAwarePaginator<int, TrabajadorPagoFila>
     */
    public function obtener(TrabajadoresPagoFiltro $filtro): LengthAwarePaginator;

    public function buscarFila(int $obligacionId): ?TrabajadorPagoFila;

    public function comprobante(int $obligacionId): ?ComprobantePagoTrabajador;
}
