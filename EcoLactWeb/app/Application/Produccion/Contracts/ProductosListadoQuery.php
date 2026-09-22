<?php

namespace App\Application\Produccion\Contracts;

use App\Application\Produccion\ProductosFiltro;
use App\Application\Produccion\ProductosListadoData;

interface ProductosListadoQuery
{
    public function obtener(ProductosFiltro $filtro): ProductosListadoData;
}
