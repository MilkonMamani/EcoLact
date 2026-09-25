<?php

namespace App\Application\Acopio\Contracts;

use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\EntregasListadoData;
use App\Application\Acopio\EntregasPaginaData;

interface EntregasListadoQuery
{
    public function obtener(EntregasFiltro $filtro): EntregasListadoData;

    /** Una página de entregas (LIMIT/OFFSET en SQL), conservando los filtros en los enlaces. */
    public function paginar(EntregasFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): EntregasPaginaData;
}
