<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Un producto de un lote: lo PLANIFICADO al iniciar la producción y lo
 * OBTENIDO realmente al finalizarla (null mientras el lote está en proceso).
 * Recuerda qué receta y versión se usaron (null en lotes históricos).
 */
final class ProduccionDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $loteProduccionId,
        private readonly int $productoId,
        private readonly float $cantidadPlanificada,
        private readonly ?float $cantidadObtenida = null,
        private readonly ?int $recetaId = null,
        private readonly ?int $recetaVersion = null,
    ) {
        if ($cantidadPlanificada <= 0.0) {
            throw new InvalidArgumentException('La cantidad planificada debe ser mayor a cero.');
        }

        if ($cantidadObtenida !== null && $cantidadObtenida < 0.0) {
            throw new InvalidArgumentException('La cantidad obtenida no puede ser negativa.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function loteProduccionId(): int
    {
        return $this->loteProduccionId;
    }

    public function productoId(): int
    {
        return $this->productoId;
    }

    public function cantidadPlanificada(): float
    {
        return $this->cantidadPlanificada;
    }

    public function cantidadObtenida(): ?float
    {
        return $this->cantidadObtenida;
    }

    public function recetaId(): ?int
    {
        return $this->recetaId;
    }

    public function recetaVersion(): ?int
    {
        return $this->recetaVersion;
    }

    public function conCantidadObtenida(float $cantidadObtenida): self
    {
        return new self(
            id: $this->id,
            loteProduccionId: $this->loteProduccionId,
            productoId: $this->productoId,
            cantidadPlanificada: $this->cantidadPlanificada,
            cantidadObtenida: $cantidadObtenida,
            recetaId: $this->recetaId,
            recetaVersion: $this->recetaVersion,
        );
    }
}
