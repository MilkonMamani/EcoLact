<?php

namespace App\Domain\Shared\Enums;

enum EstadoPago: string
{
    case REGISTRADO = 'REGISTRADO';
    case CONFIRMADO = 'CONFIRMADO';
    case ANULADO = 'ANULADO';
}
