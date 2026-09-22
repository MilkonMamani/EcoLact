<?php

namespace App\Domain\Shared\Enums;

/**
 * Motivo (causa) de un movimiento de insumo. La dirección (ENTRADA/SALIDA)
 * se guarda aparte en `tipo`; aquí se declara la que cada motivo impone, si
 * es fija. REVERSO y OTRO pueden ir en cualquiera de las dos direcciones.
 */
enum MotivoMovimientoInsumo: string
{
    case INGRESO_ACOPIO = 'INGRESO_ACOPIO';
    case INGRESO_COMPRA = 'INGRESO_COMPRA';
    case CONSUMO_PRODUCCION = 'CONSUMO_PRODUCCION';
    case AJUSTE_ENTRADA = 'AJUSTE_ENTRADA';
    case AJUSTE_SALIDA = 'AJUSTE_SALIDA';
    case REVERSO = 'REVERSO';
    case OTRO = 'OTRO';

    public const ENTRADA = 'ENTRADA';

    public const SALIDA = 'SALIDA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO_ACOPIO => 'Ingreso por acopio',
            self::INGRESO_COMPRA => 'Compra',
            self::CONSUMO_PRODUCCION => 'Consumo en producción',
            self::AJUSTE_ENTRADA => 'Ajuste (entrada)',
            self::AJUSTE_SALIDA => 'Ajuste (salida)',
            self::REVERSO => 'Reverso',
            self::OTRO => 'Otro',
        };
    }

    public function direccionFija(): ?string
    {
        return match ($this) {
            self::INGRESO_ACOPIO, self::INGRESO_COMPRA, self::AJUSTE_ENTRADA => self::ENTRADA,
            self::CONSUMO_PRODUCCION, self::AJUSTE_SALIDA => self::SALIDA,
            self::REVERSO, self::OTRO => null,
        };
    }
}
