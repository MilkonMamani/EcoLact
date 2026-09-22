<?php

namespace App\Domain\Calidad;

final class AnalisisCalidadDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $analisisId,
        private readonly int $parametroId,
        private readonly float $valor,
        private readonly string $origen,
        private readonly ?float $confianzaOcr = null,
        private readonly ?bool $cumple = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function analisisId(): int
    {
        return $this->analisisId;
    }

    public function parametroId(): int
    {
        return $this->parametroId;
    }

    public function valor(): float
    {
        return $this->valor;
    }

    public function origen(): string
    {
        return $this->origen;
    }

    public function confianzaOcr(): ?float
    {
        return $this->confianzaOcr;
    }

    public function cumple(): ?bool
    {
        return $this->cumple;
    }
}
