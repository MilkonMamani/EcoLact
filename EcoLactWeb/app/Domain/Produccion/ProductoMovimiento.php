<?php

namespace App\Domain\Produccion;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Movimiento de inventario de un producto terminado. El stock de un producto
 * es SIEMPRE la suma con signo de sus movimientos (no existe una tabla de
 * saldo aparte): suman INGRESO_PRODUCCION y AJUSTE_ENTRADA; restan VENTA y
 * AJUSTE_SALIDA.
 */
final class ProductoMovimiento
{
    public const TIPOS_ENTRADA = ['INGRESO_PRODUCCION', 'AJUSTE_ENTRADA'];

    public const TIPOS_SALIDA = ['VENTA', 'AJUSTE_SALIDA'];

    public function __construct(
        private readonly ?int $id,
        private readonly int $productoId,
        private readonly string $tipo,
        private readonly float $cantidad,
        private readonly ?int $loteProduccionId,
        private readonly ?int $ventaProductoId,
        private readonly DateTimeImmutable $fecha,
        private readonly int $registradoPorUserId,
        private readonly ?string $observacion = null,
    ) {
        if (! in_array($tipo, [...self::TIPOS_ENTRADA, ...self::TIPOS_SALIDA], true)) {
            throw new InvalidArgumentException('El tipo de movimiento de producto no es válido.');
        }

        if ($cantidad <= 0.0) {
            throw new InvalidArgumentException('La cantidad del movimiento de producto debe ser mayor a cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function productoId(): int
    {
        return $this->productoId;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function esEntrada(): bool
    {
        return in_array($this->tipo, self::TIPOS_ENTRADA, true);
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function loteProduccionId(): ?int
    {
        return $this->loteProduccionId;
    }

    public function ventaProductoId(): ?int
    {
        return $this->ventaProductoId;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }
}
