<?php

namespace App\Application\Zona\Contracts;

use App\Application\Zona\ZonasFiltro;
use App\Application\Zona\ZonasListadoData;

interface ZonasListadoQuery
{
    public function obtener(ZonasFiltro $filtro): ZonasListadoData;
}
