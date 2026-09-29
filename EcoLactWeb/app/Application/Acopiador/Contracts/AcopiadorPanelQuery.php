<?php

namespace App\Application\Acopiador\Contracts;

use App\Application\Acopiador\AcopiadorPanelData;

interface AcopiadorPanelQuery
{
    /** Todo lo que muestra el panel lateral de un acopiador; null si no existe. */
    public function obtener(int $acopiadorId): ?AcopiadorPanelData;
}
