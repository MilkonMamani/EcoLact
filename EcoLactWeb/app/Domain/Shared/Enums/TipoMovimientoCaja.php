<?php

namespace App\Domain\Shared\Enums;

enum TipoMovimientoCaja: string
{
    case ENTRADA = 'ENTRADA';
    case SALIDA = 'SALIDA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENTRADA => 'Entrada',
            self::SALIDA => 'Salida',
        };
    }
}
