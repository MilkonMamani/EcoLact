<?php

namespace App\Domain\Shared\Enums;

enum EstadoAnalisis: string
{
    case BORRADOR = 'BORRADOR';
    case CONFIRMADO = 'CONFIRMADO';
}
