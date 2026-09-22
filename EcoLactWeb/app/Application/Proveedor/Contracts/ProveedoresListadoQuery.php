<?php

namespace App\Application\Proveedor\Contracts;

use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Proveedor\ProveedoresListadoData;

interface ProveedoresListadoQuery
{
    public function obtener(ProveedoresFiltro $filtro): ProveedoresListadoData;
}
