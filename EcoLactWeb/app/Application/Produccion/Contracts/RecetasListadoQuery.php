<?php

namespace App\Application\Produccion\Contracts;

use App\Application\Produccion\RecetasListadoData;

interface RecetasListadoQuery
{
    /**
     * @param  array{producto_id?: int|null, estado?: string|null, busqueda?: string|null}  $filtros
     */
    public function obtener(array $filtros = []): RecetasListadoData;
}
