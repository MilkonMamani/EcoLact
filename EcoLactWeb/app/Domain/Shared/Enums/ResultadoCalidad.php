<?php

namespace App\Domain\Shared\Enums;

enum ResultadoCalidad: string
{
    case ACEPTADA = 'ACEPTADA';
    case OBSERVADA = 'OBSERVADA';
    case RECHAZADA = 'RECHAZADA';
}
