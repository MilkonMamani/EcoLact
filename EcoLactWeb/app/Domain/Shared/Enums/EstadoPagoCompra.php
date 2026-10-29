<?php

namespace App\Domain\Shared\Enums;

/**
 * Situación financiera de una compra, derivada SIEMPRE de su total y de la
 * suma de sus pagos (pagos_compra). SIN_IMPORTE: compra sin total conocido
 * (solo las registradas sin precios): no se puede pagar ni mueve caja.
 */
enum EstadoPagoCompra: string
{
    case PENDIENTE = 'PENDIENTE';
    case PARCIAL = 'PARCIAL';
    case PAGADA = 'PAGADA';
    case SIN_IMPORTE = 'SIN_IMPORTE';

    public static function desde(?float $total, float $pagado): self
    {
        $pagado = round($pagado, 2);

        return match (true) {
            $total === null || round($total, 2) <= 0.0 => self::SIN_IMPORTE,
            $pagado >= round($total, 2) => self::PAGADA,
            $pagado > 0.0 => self::PARCIAL,
            default => self::PENDIENTE,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::PARCIAL => 'Pago parcial',
            self::PAGADA => 'Pagada',
            self::SIN_IMPORTE => 'Sin importe',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(array_map(fn (self $caso): array => ['v' => $caso->value, 'e' => $caso->etiqueta()], self::cases()), 'e', 'v');
    }
}
