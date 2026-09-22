<?php

namespace App\Domain\Shared\Enums;

enum EstadoEntrega: string
{
    case PENDIENTE_ANALISIS = 'PENDIENTE_ANALISIS';
    case ANALIZADA = 'ANALIZADA';
    case ANULADA = 'ANULADA';
}
