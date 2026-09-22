<?php

namespace App\Domain\Shared\Enums;

enum EstadoUsuario: string
{
    case ACTIVO = 'ACTIVO';
    case INACTIVO = 'INACTIVO';
}
