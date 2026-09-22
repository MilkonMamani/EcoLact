<?php

namespace App\Application\Recorrido\Contracts;

use App\Application\Recorrido\RecorridoVista;

interface RecorridoVistaQuery
{
    public function obtener(int $recorridoId): RecorridoVista;
}
