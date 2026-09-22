<?php

namespace App\Application\Liquidacion\Contracts;

use App\Application\Liquidacion\PagosFiltro;
use App\Application\Liquidacion\PagosListadoData;

interface PagosListadoQuery
{
    public function obtener(PagosFiltro $filtro): PagosListadoData;
}
