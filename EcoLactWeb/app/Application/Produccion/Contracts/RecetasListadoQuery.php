<?php

namespace App\Application\Produccion\Contracts;

use App\Application\Produccion\RecetasListadoData;

interface RecetasListadoQuery
{
    /**
     * Con `solo_vigente` se devuelve una sola receta por producto: la activa o,
     * si ninguna lo está, la más reciente. Así la pantalla muestra UNA receta
     * actual por producto aunque el historial se conserve para las producciones.
     *
     * @param  array{producto_id?: int|null, estado?: string|null, busqueda?: string|null, solo_vigente?: bool}  $filtros
     */
    public function obtener(array $filtros = []): RecetasListadoData;
}
