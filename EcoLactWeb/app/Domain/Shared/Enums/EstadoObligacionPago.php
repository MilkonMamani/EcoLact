<?php

namespace App\Domain\Shared\Enums;

enum EstadoObligacionPago: string
{
    case EN_CURSO = 'EN_CURSO';
    case PENDIENTE = 'PENDIENTE';
    case PAGADO = 'PAGADO';
    /** Semana de un proveedor con una entrega RECHAZADA: queda registrada con S/ 0.00 y nunca se paga. */
    case NO_PAGABLE = 'NO_PAGABLE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EN_CURSO => 'En curso',
            self::PENDIENTE => 'Pendiente de pago',
            self::PAGADO => 'Pagado',
            self::NO_PAGABLE => 'No pagable',
        };
    }
}
