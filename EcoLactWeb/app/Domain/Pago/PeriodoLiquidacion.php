<?php

namespace App\Domain\Pago;

use DateTimeImmutable;
use InvalidArgumentException;

final class PeriodoLiquidacion
{
    public function __construct(
        private readonly ?int $id,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly DateTimeImmutable $fechaFin,
        private readonly string $estado,
    ) {
        if ($fechaFin < $fechaInicio) {
            throw new InvalidArgumentException('La fecha fin del periodo no puede ser anterior a la fecha inicio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function estado(): string
    {
        return $this->estado;
    }
}
