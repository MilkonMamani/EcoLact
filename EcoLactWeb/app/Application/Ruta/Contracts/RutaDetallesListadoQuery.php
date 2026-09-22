<?php

namespace App\Application\Ruta\Contracts;

use App\Application\Ruta\RutaDetalleFila;

interface RutaDetallesListadoQuery
{
    /**
     * @return RutaDetalleFila[] ordenadas por orden_visita ascendente
     */
    public function obtener(int $rutaId): array;
}
