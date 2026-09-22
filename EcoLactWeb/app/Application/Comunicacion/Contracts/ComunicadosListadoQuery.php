<?php

namespace App\Application\Comunicacion\Contracts;

use App\Application\Comunicacion\ComunicadosFiltro;
use App\Application\Comunicacion\ComunicadosListadoData;

interface ComunicadosListadoQuery
{
    public function obtener(ComunicadosFiltro $filtro): ComunicadosListadoData;
}
