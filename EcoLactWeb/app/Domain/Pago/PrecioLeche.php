<?php

namespace App\Domain\Pago;

use DateTimeImmutable;
use InvalidArgumentException;

final class PrecioLeche
{
    public function __construct(
        private readonly ?int $id,
        private readonly float $precioLitro,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly string $estado,
        private readonly int $creadoPorUserId,
    ) {
        if ($precioLitro <= 0.0) {
            throw new InvalidArgumentException('El precio por litro debe ser mayor a cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function precioLitro(): float
    {
        return $this->precioLitro;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function creadoPorUserId(): int
    {
        return $this->creadoPorUserId;
    }

    public function estaVigente(DateTimeImmutable $fecha): bool
    {
        if ($this->estado !== 'ACTIVO') {
            return false;
        }

        if ($this->fechaInicio > $fecha) {
            return false;
        }

        return $this->fechaFin === null || $this->fechaFin >= $fecha;
    }
}
