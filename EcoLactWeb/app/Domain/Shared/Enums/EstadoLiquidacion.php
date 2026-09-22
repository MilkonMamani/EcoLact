<?php

namespace App\Domain\Shared\Enums;

enum EstadoLiquidacion: string
{
    case PENDIENTE = 'PENDIENTE';
    case PARCIALMENTE_PAGADA = 'PARCIALMENTE_PAGADA';
    case PAGADA = 'PAGADA';
    case ANULADA = 'ANULADA';
}
