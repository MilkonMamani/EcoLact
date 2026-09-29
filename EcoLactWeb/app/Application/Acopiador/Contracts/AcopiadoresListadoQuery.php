<?php

namespace App\Application\Acopiador\Contracts;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\AcopiadoresListadoData;
use App\Application\Acopiador\AcopiadoresPaginaData;

interface AcopiadoresListadoQuery
{
    public function obtener(AcopiadoresFiltro $filtro): AcopiadoresListadoData;

    /** Pantalla de administración: una página de acopiadores con su conteo de entregas. */
    public function paginar(AcopiadoresFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): AcopiadoresPaginaData;
}
