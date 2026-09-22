<?php

namespace App\Domain\Pago;

final class LiquidacionDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $liquidacionId,
        private readonly int $valorizacionEntregaId,
        private readonly float $importe,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function liquidacionId(): int
    {
        return $this->liquidacionId;
    }

    public function valorizacionEntregaId(): int
    {
        return $this->valorizacionEntregaId;
    }

    public function importe(): float
    {
        return $this->importe;
    }
}
