<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Agrupa los lotes iniciados juntos en una sola operación de "Nueva
 * producción". No guarda productos ni consumos: cada lote conserva los suyos
 * y se finaliza por separado.
 */
final class PlanificacionProduccion
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $codigo,
        private readonly int $registradoPorUserId,
        private readonly ?string $observacion = null,
    ) {
        if (trim($codigo) === '') {
            throw new InvalidArgumentException('El código de la planificación es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function conCodigo(string $codigo): self
    {
        return new self($this->id, $codigo, $this->registradoPorUserId, $this->observacion);
    }
}
