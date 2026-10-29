<?php

namespace App\Domain\Shared\Enums;

/**
 * Si los artículos de inventario de una compra ya llegaron. Mientras está
 * PENDIENTE no hay stock; al confirmarse la recepción (RecibirCompra) entra
 * una sola vez. NO_APLICA: la compra no tiene líneas de inventario.
 */
enum EstadoRecepcionCompra: string
{
    case PENDIENTE = 'PENDIENTE';
    case RECIBIDA = 'RECIBIDA';
    case NO_APLICA = 'NO_APLICA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Por recibir',
            self::RECIBIDA => 'Recibida',
            self::NO_APLICA => 'No aplica',
        };
    }
}
