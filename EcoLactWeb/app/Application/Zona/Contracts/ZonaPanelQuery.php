<?php

namespace App\Application\Zona\Contracts;

use App\Application\Zona\ZonaPanelData;

interface ZonaPanelQuery
{
    /** Todo lo que muestra el panel lateral de una zona; null si no existe. */
    public function obtener(int $zonaId): ?ZonaPanelData;
}
