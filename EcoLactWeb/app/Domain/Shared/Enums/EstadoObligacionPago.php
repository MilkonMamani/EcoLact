<?php

namespace App\Domain\Shared\Enums;

enum EstadoObligacionPago: string
{
    case EN_CURSO = 'EN_CURSO';
    case PENDIENTE = 'PENDIENTE';
    case PAGADO = 'PAGADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EN_CURSO => 'En curso',
            self::PENDIENTE => 'Pendiente de pago',
            self::PAGADO => 'Pagado',
        };
    }
}
