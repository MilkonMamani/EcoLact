<?php

namespace App\Domain\Calidad;

use DateTimeImmutable;
use InvalidArgumentException;

final class Sancion
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $proveedorId,
        private readonly ?int $analisisId,
        private readonly string $tipo,
        private readonly ?int $nivelAdvertencia,
        private readonly string $descripcion,
        private readonly string $tipoPenalizacion,
        private readonly ?float $valorPenalizacion,
        private readonly DateTimeImmutable $fecha,
        private readonly string $estado,
        private readonly int $creadoPorUserId,
    ) {
        if (trim($tipo) === '') {
            throw new InvalidArgumentException('El tipo de sanción es obligatorio.');
        }

        if (trim($descripcion) === '') {
            throw new InvalidArgumentException('La descripción de la sanción es obligatoria.');
        }

        if ($nivelAdvertencia !== null && ($nivelAdvertencia < 1 || $nivelAdvertencia > 3)) {
            throw new InvalidArgumentException('El nivel de advertencia debe estar entre 1 y 3.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
    }

    public function analisisId(): ?int
    {
        return $this->analisisId;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function nivelAdvertencia(): ?int
    {
        return $this->nivelAdvertencia;
    }

    public function descripcion(): string
    {
        return $this->descripcion;
    }

    public function tipoPenalizacion(): string
    {
        return $this->tipoPenalizacion;
    }

    public function valorPenalizacion(): ?float
    {
        return $this->valorPenalizacion;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function creadoPorUserId(): int
    {
        return $this->creadoPorUserId;
    }
}
