<?php

namespace App\Domain\Pago;

final class AjusteLiquidacion
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $liquidacionId,
        private readonly ?int $sancionId,
        private readonly string $tipo,
        private readonly string $descripcion,
        private readonly float $monto,
        private readonly int $creadoPorUserId,
        private readonly ?int $ventaProductoId = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function liquidacionId(): int
    {
        return $this->liquidacionId;
    }

    public function sancionId(): ?int
    {
        return $this->sancionId;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function descripcion(): string
    {
        return $this->descripcion;
    }

    public function monto(): float
    {
        return $this->monto;
    }

    public function creadoPorUserId(): int
    {
        return $this->creadoPorUserId;
    }

    public function ventaProductoId(): ?int
    {
        return $this->ventaProductoId;
    }
}
