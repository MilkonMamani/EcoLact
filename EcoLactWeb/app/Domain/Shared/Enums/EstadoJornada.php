<?php

namespace App\Domain\Shared\Enums;

enum EstadoJornada: string
{
    case PRESENTE = 'PRESENTE';
    case FALTA = 'FALTA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PRESENTE => 'Presente',
            self::FALTA => 'Falta',
        };
    }
}
