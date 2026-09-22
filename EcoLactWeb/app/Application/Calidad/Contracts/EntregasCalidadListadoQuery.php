<?php

namespace App\Application\Calidad\Contracts;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\EntregasCalidadListadoData;

interface EntregasCalidadListadoQuery
{
    /**
     * Entregas válidas (no anuladas) con su análisis si lo tienen. El filtro de
     * fecha aplica a la fecha de la ENTREGA.
     */
    public function obtener(AnalisisCalidadFiltro $filtro): EntregasCalidadListadoData;
}
