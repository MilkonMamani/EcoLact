<?php

namespace App\Domain\Ruta;

use DateTimeImmutable;

final class ProveedorRutaHistorial
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $proveedorId,
        private readonly int $rutaId,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly ?string $motivo,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
    }

    public function rutaId(): int
    {
        return $this->rutaId;
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
}
