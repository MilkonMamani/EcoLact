<?php

namespace App\Application\Produccion\Contracts;

use App\Application\Produccion\ProductoDetalleData;

interface ProductoDetalleQuery
{
    public function obtener(int $productoId): ?ProductoDetalleData;
}
