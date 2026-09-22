<?php

namespace App\Application\Acopio\Contracts;

use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\EntregasListadoData;

interface EntregasListadoQuery
{
    public function obtener(EntregasFiltro $filtro): EntregasListadoData;
}
