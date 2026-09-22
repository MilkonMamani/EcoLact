<?php

namespace App\Application\Calidad\Contracts;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\AnalisisCalidadListadoData;

interface AnalisisCalidadListadoQuery
{
    public function obtener(AnalisisCalidadFiltro $filtro): AnalisisCalidadListadoData;
}
