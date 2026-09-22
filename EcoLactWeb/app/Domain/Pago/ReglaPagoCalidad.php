<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;

final class ReglaPagoCalidad
{
    public function __construct(
        private readonly ?int $id,
        private readonly ResultadoCalidad $resultadoCalidad,
        private readonly string $tipoAjuste,
        private readonly float $valorAjuste,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly string $estado,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function resultadoCalidad(): ResultadoCalidad
    {
        return $this->resultadoCalidad;
    }

    public function tipoAjuste(): string
    {
        return $this->tipoAjuste;
    }

    public function valorAjuste(): float
    {
        return $this->valorAjuste;
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

    /**
     * Calcula el ajuste (en soles) que corresponde aplicar a un precio base por litro.
     */
    public function calcularAjuste(float $precioBaseLitro): float
    {
        return $this->tipoAjuste === 'PORCENTAJE'
            ? round($precioBaseLitro * ($this->valorAjuste / 100), 4)
            : $this->valorAjuste;
    }
}
