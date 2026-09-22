<?php

namespace App\Domain\Pago;

use DateTimeImmutable;
use InvalidArgumentException;

final class ValorizacionEntrega
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $entregaId,
        private readonly ?int $analisisId,
        private readonly int $precioLecheId,
        private readonly ?int $reglaPagoId,
        private readonly float $litrosEntregados,
        private readonly float $litrosPagables,
        private readonly float $precioBaseLitro,
        private readonly float $ajustePorLitro,
        private readonly float $precioFinalLitro,
        private readonly float $importeTotal,
        private readonly DateTimeImmutable $fechaCalculo,
    ) {
        if ($litrosEntregados <= 0.0) {
            throw new InvalidArgumentException('Los litros entregados a valorizar deben ser mayores a cero.');
        }

        if ($litrosPagables < 0.0) {
            throw new InvalidArgumentException('Los litros pagables no pueden ser negativos.');
        }

        if ($precioFinalLitro < 0.0) {
            throw new InvalidArgumentException('El precio final por litro no puede ser negativo.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function entregaId(): int
    {
        return $this->entregaId;
    }

    public function analisisId(): ?int
    {
        return $this->analisisId;
    }

    public function precioLecheId(): int
    {
        return $this->precioLecheId;
    }

    public function reglaPagoId(): ?int
    {
        return $this->reglaPagoId;
    }

    public function litrosEntregados(): float
    {
        return $this->litrosEntregados;
    }

    public function litrosPagables(): float
    {
        return $this->litrosPagables;
    }

    public function precioBaseLitro(): float
    {
        return $this->precioBaseLitro;
    }

    public function ajustePorLitro(): float
    {
        return $this->ajustePorLitro;
    }

    public function precioFinalLitro(): float
    {
        return $this->precioFinalLitro;
    }

    public function importeTotal(): float
    {
        return $this->importeTotal;
    }

    public function fechaCalculo(): DateTimeImmutable
    {
        return $this->fechaCalculo;
    }
}
