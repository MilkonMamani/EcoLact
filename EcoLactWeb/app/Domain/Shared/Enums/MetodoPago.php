<?php

namespace App\Domain\Shared\Enums;

enum MetodoPago: string
{
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case EFECTIVO = 'EFECTIVO';
    case YAPE = 'YAPE';
    case PLIN = 'PLIN';
    case DEPOSITO = 'DEPOSITO';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRANSFERENCIA => 'Transferencia',
            self::EFECTIVO => 'Efectivo',
            self::YAPE => 'Yape',
            self::PLIN => 'Plin',
            self::DEPOSITO => 'Depósito',
            self::OTRO => 'Otro',
        };
    }

    /**
     * Los métodos electrónicos dejan una operación rastreable, por lo que su
     * número de operación/referencia es obligatorio. Efectivo y Otro no.
     */
    public function requiereNumeroOperacion(): bool
    {
        return match ($this) {
            self::TRANSFERENCIA, self::YAPE, self::PLIN, self::DEPOSITO => true,
            self::EFECTIVO, self::OTRO => false,
        };
    }

    /**
     * @return array<string, string> valor => etiqueta
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $metodo) {
            $opciones[$metodo->value] = $metodo->etiqueta();
        }

        return $opciones;
    }
}
