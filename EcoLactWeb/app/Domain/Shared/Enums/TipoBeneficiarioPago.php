<?php

namespace App\Domain\Shared\Enums;

enum TipoBeneficiarioPago: string
{
    case PROVEEDOR = 'PROVEEDOR';
    case PERSONAL = 'PERSONAL';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PROVEEDOR => 'Proveedor',
            self::PERSONAL => 'Personal',
        };
    }
}
