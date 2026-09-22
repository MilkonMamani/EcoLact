<?php

namespace App\Domain\Shared\Enums;

enum EstadoRecorrido: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_PROGRESO = 'EN_PROGRESO';
    case FINALIZADO = 'FINALIZADO';
    case CANCELADO = 'CANCELADO';

    public function estaAbierto(): bool
    {
        return $this === self::PENDIENTE || $this === self::EN_PROGRESO;
    }
}
