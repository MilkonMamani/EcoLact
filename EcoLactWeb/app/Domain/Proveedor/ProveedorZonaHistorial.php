<?php

namespace App\Domain\Proveedor;

use DateTimeImmutable;

final class ProveedorZonaHistorial
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $proveedorId,
        private readonly int $zonaId,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly ?string $motivo = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
    }

    public function zonaId(): int
    {
        return $this->zonaId;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function motivo(): ?string
    {
        return $this->motivo;
    }

    public function estaVigente(): bool
    {
        return $this->fechaFin === null;
    }
}
