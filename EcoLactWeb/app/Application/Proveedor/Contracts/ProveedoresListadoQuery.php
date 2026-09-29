<?php

namespace App\Application\Proveedor\Contracts;

use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Proveedor\ProveedoresListadoData;
use App\Application\Proveedor\ProveedoresPaginaData;

interface ProveedoresListadoQuery
{
    public function obtener(ProveedoresFiltro $filtro): ProveedoresListadoData;

    /** Pantalla de administración: una página de proveedores con su última entrega y litros acumulados. */
    public function paginar(ProveedoresFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): ProveedoresPaginaData;
}
