<?php

namespace App\Domain\Entrega;

use App\Domain\Shared\Enums\EstadoEntrega;
use DateTimeImmutable;
use InvalidArgumentException;

final class Entrega
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $proveedorId,
        private readonly ?int $acopiadorId,
        private readonly int $zonaId,
        private readonly int $registradoPorUserId,
        private readonly DateTimeImmutable $fechaHora,
        private readonly float $litros,
        private readonly string $metodoIdentificacion,
        private readonly EstadoEntrega $estado,
        private readonly ?string $observacion = null,
        private readonly ?int $recorridoDetalleId = null,
        private readonly ?string $tokenIdempotencia = null,
    ) {
        if ($litros <= 0.0) {
            throw new InvalidArgumentException('Los litros entregados deben ser mayores a cero.');
        }

        if (trim($metodoIdentificacion) === '') {
            throw new InvalidArgumentException('El método de identificación es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
    }

    public function acopiadorId(): ?int
    {
        return $this->acopiadorId;
    }

    public function zonaId(): int
    {
        return $this->zonaId;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function fechaHora(): DateTimeImmutable
    {
        return $this->fechaHora;
    }

    public function litros(): float
    {
        return $this->litros;
    }

    public function metodoIdentificacion(): string
    {
        return $this->metodoIdentificacion;
    }

    public function estado(): EstadoEntrega
    {
        return $this->estado;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function recorridoDetalleId(): ?int
    {
        return $this->recorridoDetalleId;
    }

    public function tokenIdempotencia(): ?string
    {
        return $this->tokenIdempotencia;
    }
}
