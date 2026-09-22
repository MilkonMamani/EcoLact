<?php

namespace App\Application\Liquidacion\Contracts;

use App\Application\Liquidacion\LiquidacionesFiltro;
use App\Application\Liquidacion\LiquidacionesListadoData;

interface LiquidacionesListadoQuery
{
    public function obtener(LiquidacionesFiltro $filtro): LiquidacionesListadoData;
}
