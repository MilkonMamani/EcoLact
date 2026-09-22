<?php

namespace App\Domain\Acopiador;

use DateTimeImmutable;

final class AcopiadorZonaHistorial
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $acopiadorId,
        private readonly int $zonaId,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly ?string $motivo = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function acopiadorId(): int
    {
        return $this->acopiadorId;
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
