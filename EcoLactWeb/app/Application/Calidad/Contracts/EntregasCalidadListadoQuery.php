<?php

namespace App\Application\Calidad\Contracts;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\EntregasCalidadListadoData;
use App\Application\Calidad\EntregasCalidadPaginaData;

interface EntregasCalidadListadoQuery
{
    /**
     * Entregas válidas (no anuladas) con su análisis si lo tienen. El filtro de
     * fecha aplica a la fecha de la ENTREGA.
     */
    public function obtener(AnalisisCalidadFiltro $filtro): EntregasCalidadListadoData;

    /**
     * Mismas filas y filtros que obtener(), paginadas en el servidor y
     * ordenadas según el filtro (fecha, proveedor o resultado).
     */
    public function paginar(AnalisisCalidadFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): EntregasCalidadPaginaData;
}
