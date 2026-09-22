<?php

namespace App\Application\Acopiador\Contracts;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\AcopiadoresListadoData;

interface AcopiadoresListadoQuery
{
    public function obtener(AcopiadoresFiltro $filtro): AcopiadoresListadoData;
}
