<?php

namespace App\Domain\Shared\Enums;

/**
 * Qué efecto tiene cada línea de una compra. Solo INVENTARIO necesita un
 * insumo y una presentación y aumenta el stock (al recibirse); el resto es
 * gasto: se registra y se paga, pero no toca Inventario.
 */
enum TratamientoCompra: string
{
    case INVENTARIO = 'INVENTARIO';
    case CONSUMO = 'CONSUMO';
    case BIEN = 'BIEN';
    case SERVICIO = 'SERVICIO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INVENTARIO => 'Ingreso de inventario',
            self::CONSUMO => 'Gasto de consumo inmediato',
            self::BIEN => 'Adquisición de bien o equipo',
            self::SERVICIO => 'Servicio',
        };
    }

    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::INVENTARIO => 'Inventario',
            self::CONSUMO => 'Consumo',
            self::BIEN => 'Bien o equipo',
            self::SERVICIO => 'Servicio',
        };
    }

    public function generaStock(): bool
    {
        return $this === self::INVENTARIO;
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(array_map(fn (self $caso): array => ['v' => $caso->value, 'e' => $caso->etiqueta()], self::cases()), 'e', 'v');
    }
}
