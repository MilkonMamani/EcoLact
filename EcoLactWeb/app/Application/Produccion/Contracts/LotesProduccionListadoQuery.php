<?php

namespace App\Application\Produccion\Contracts;

use App\Application\Produccion\LotesProduccionFiltro;
use App\Application\Produccion\LotesProduccionListadoData;

interface LotesProduccionListadoQuery
{
    public function obtener(LotesProduccionFiltro $filtro): LotesProduccionListadoData;
}
