<?php

namespace App\Domain\Shared\Enums;

enum OrigenJornada: string
{
    case MANUAL = 'MANUAL';
    case AUTOMATICO = 'AUTOMATICO';
    case QR = 'QR';
    case CORREGIDO = 'CORREGIDO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::AUTOMATICO => 'Automático',
            self::QR => 'QR',
            self::CORREGIDO => 'Corregido',
        };
    }
}
