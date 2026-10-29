<?php

namespace App\Domain\Compra;

use App\Domain\Shared\Enums\MetodoPago;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un desembolso real hecho por una compra (contado, cuota o saldo final).
 * Cada pago genera exactamente UN movimiento de Caja (PAGO_COMPRA #id), cuyo
 * id queda en `movimientoCajaId`. Es un registro histórico: no se edita.
 *
 * `metodoPago` es null solo en pagos históricos migrados (antes no se pedía).
 */
final class PagoCompra
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $compraInsumoId,
        private readonly float $monto,
        private readonly ?MetodoPago $metodoPago,
        private readonly DateTimeImmutable $fecha,
        private readonly int $registradoPorUserId,
        private readonly ?string $numeroOperacion = null,
        private readonly ?string $observacion = null,
        private readonly ?int $movimientoCajaId = null,
    ) {
        if (round($monto, 2) <= 0.0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor que cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function compraInsumoId(): int
    {
        return $this->compraInsumoId;
    }

    public function monto(): float
    {
        return round($this->monto, 2);
    }

    public function metodoPago(): ?MetodoPago
    {
        return $this->metodoPago;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function numeroOperacion(): ?string
    {
        return $this->numeroOperacion;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function movimientoCajaId(): ?int
    {
        return $this->movimientoCajaId;
    }

    /** "PC-0003": referencia del pago en Caja. */
    public function codigo(): string
    {
        return 'PC-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
