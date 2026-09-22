<?php

namespace App\Domain\Calidad;

use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;

final class ReglaCalidad
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $parametroId,
        private readonly ?float $valorMinimo,
        private readonly ?float $valorMaximo,
        private readonly ResultadoCalidad $resultadoIncumplimiento,
        private readonly int $prioridad,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly string $estado,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function parametroId(): int
    {
        return $this->parametroId;
    }

    public function valorMinimo(): ?float
    {
        return $this->valorMinimo;
    }

    public function valorMaximo(): ?float
    {
        return $this->valorMaximo;
    }

    public function resultadoIncumplimiento(): ResultadoCalidad
    {
        return $this->resultadoIncumplimiento;
    }

    public function prioridad(): int
    {
        return $this->prioridad;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function estaVigente(DateTimeImmutable $fecha): bool
    {
        if ($this->estado !== 'ACTIVO') {
            return false;
        }

        if ($this->fechaInicio > $fecha) {
            return false;
        }

        return $this->fechaFin === null || $this->fechaFin >= $fecha;
    }

    public function cumple(float $valor): bool
    {
        if ($this->valorMinimo !== null && $valor < $this->valorMinimo) {
            return false;
        }

        if ($this->valorMaximo !== null && $valor > $this->valorMaximo) {
            return false;
        }

        return true;
    }
}
