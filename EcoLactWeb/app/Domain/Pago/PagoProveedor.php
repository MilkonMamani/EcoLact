<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\EstadoPago;
use DateTimeImmutable;
use InvalidArgumentException;

final class PagoProveedor
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $liquidacionId,
        private readonly DateTimeImmutable $fechaPago,
        private readonly float $monto,
        private readonly EstadoPago $estado,
        private readonly int $registradoPorUserId,
        private readonly ?string $observacion = null,
    ) {
        if ($monto <= 0.0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function liquidacionId(): int
    {
        return $this->liquidacionId;
    }

    public function fechaPago(): DateTimeImmutable
    {
        return $this->fechaPago;
    }

    public function monto(): float
    {
        return $this->monto;
    }

    public function estado(): EstadoPago
    {
        return $this->estado;
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
