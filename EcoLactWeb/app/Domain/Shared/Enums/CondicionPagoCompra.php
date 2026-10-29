<?php

namespace App\Domain\Shared\Enums;

/**
 * Cómo se paga una compra al registrarla. Solo lo que realmente se paga
 * sale de Caja: CREDITO no mueve dinero y PARCIAL mueve solo el monto inicial.
 */
enum CondicionPagoCompra: string
{
    case CONTADO = 'CONTADO';
    case CREDITO = 'CREDITO';
    case PARCIAL = 'PARCIAL';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CONTADO => 'Pago total',
            self::CREDITO => 'A crédito',
            self::PARCIAL => 'Pago parcial',
        };
    }
}
