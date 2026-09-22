<?php

namespace App\Domain\Shared\Enums;

enum PeriodicidadPago: string
{
    case SEMANAL = 'SEMANAL';
    case MENSUAL = 'MENSUAL';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SEMANAL => 'Semanal',
            self::MENSUAL => 'Mensual',
        };
    }
}
