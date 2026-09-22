<?php

namespace App\Domain\Shared\Enums;

enum TipoCalculoPago: string
{
    case POR_LITROS = 'POR_LITROS';
    case SUELDO_FIJO = 'SUELDO_FIJO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::POR_LITROS => 'Por litros entregados',
            self::SUELDO_FIJO => 'Sueldo fijo mensual',
        };
    }
}
