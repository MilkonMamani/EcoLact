<?php

namespace App\Domain\Shared\Enums;

enum EstadoParadaRecorrido: string
{
    case PENDIENTE = 'PENDIENTE';
    case RECOLECTADO = 'RECOLECTADO';
    case NO_ENTREGO = 'NO_ENTREGO';
}
