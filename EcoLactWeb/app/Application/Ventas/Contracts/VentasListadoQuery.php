<?php

namespace App\Application\Ventas\Contracts;

use App\Application\Ventas\VentasFiltro;
use App\Application\Ventas\VentasListadoData;

interface VentasListadoQuery
{
    public function obtener(VentasFiltro $filtro): VentasListadoData;
}
