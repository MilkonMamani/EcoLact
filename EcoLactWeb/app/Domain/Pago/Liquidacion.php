<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\EstadoLiquidacion;
use DateTimeImmutable;
use InvalidArgumentException;

final class Liquidacion
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $periodoId,
        private readonly int $proveedorId,
        private readonly float $totalLitrosEntregados,
        private readonly float $totalLitrosPagables,
        private readonly float $subtotal,
        private readonly float $totalAjustes,
        private readonly float $totalPagar,
        private readonly EstadoLiquidacion $estado,
        private readonly DateTimeImmutable $fechaCalculo,
        private readonly int $generadoPorUserId,
        private readonly float $precioLitroAplicado = 0.0,
        private readonly bool $tuvoIncidencia = false,
        private readonly ?int $sancionId = null,
    ) {
        if ($totalLitrosEntregados < 0.0 || $totalLitrosPagables < 0.0) {
            throw new InvalidArgumentException('Los litros de una liquidación no pueden ser negativos.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function periodoId(): int
    {
        return $this->periodoId;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
    }

    public function totalLitrosEntregados(): float
    {
        return $this->totalLitrosEntregados;
    }

    public function totalLitrosPagables(): float
    {
        return $this->totalLitrosPagables;
    }

    public function subtotal(): float
    {
        return $this->subtotal;
    }

    public function totalAjustes(): float
    {
        return $this->totalAjustes;
    }

    public function totalPagar(): float
    {
        return $this->totalPagar;
    }

    public function estado(): EstadoLiquidacion
    {
        return $this->estado;
    }

    public function fechaCalculo(): DateTimeImmutable
    {
        return $this->fechaCalculo;
    }

    public function generadoPorUserId(): int
    {
        return $this->generadoPorUserId;
    }

    public function precioLitroAplicado(): float
    {
        return $this->precioLitroAplicado;
    }

    public function tuvoIncidencia(): bool
    {
        return $this->tuvoIncidencia;
    }

    public function sancionId(): ?int
    {
        return $this->sancionId;
    }

    public function totalPagarCalculado(): float
    {
        return round($this->subtotal + $this->totalAjustes, 2);
    }

    public function coincideConTotalCalculado(): bool
    {
        return round($this->totalPagar, 2) === $this->totalPagarCalculado();
    }
}
