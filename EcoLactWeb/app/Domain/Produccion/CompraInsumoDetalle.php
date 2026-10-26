<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Línea de una compra: cuántas presentaciones de un insumo se compraron y su
 * total en la unidad base. Guarda una fotografía de la presentación usada
 * (nombre, contenido y unidad) para que la compra histórica no cambie si la
 * presentación se edita después.
 */
final class CompraInsumoDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $compraInsumoId,
        private readonly int $insumoId,
        private readonly ?int $presentacionInsumoId,
        private readonly string $presentacionNombre,
        private readonly float $presentacionCantidad,
        private readonly string $presentacionUnidad,
        private readonly float $cantidadPresentaciones,
        private readonly float $cantidadBase,
        private readonly string $unidadBase,
        private readonly ?int $insumoMovimientoId = null,
        private readonly ?float $precioUnitario = null,
    ) {
        if ($cantidadPresentaciones <= 0.0 || $cantidadBase <= 0.0) {
            throw new InvalidArgumentException('La cantidad comprada debe ser mayor que cero.');
        }

        if ($precioUnitario !== null && $precioUnitario < 0.0) {
            throw new InvalidArgumentException('El precio unitario no puede ser negativo.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function compraInsumoId(): ?int
    {
        return $this->compraInsumoId;
    }

    public function insumoId(): int
    {
        return $this->insumoId;
    }

    public function presentacionInsumoId(): ?int
    {
        return $this->presentacionInsumoId;
    }

    public function presentacionNombre(): string
    {
        return $this->presentacionNombre;
    }

    public function presentacionCantidad(): float
    {
        return $this->presentacionCantidad;
    }

    public function presentacionUnidad(): string
    {
        return $this->presentacionUnidad;
    }

    public function cantidadPresentaciones(): float
    {
        return $this->cantidadPresentaciones;
    }

    public function cantidadBase(): float
    {
        return $this->cantidadBase;
    }

    public function unidadBase(): string
    {
        return $this->unidadBase;
    }

    public function insumoMovimientoId(): ?int
    {
        return $this->insumoMovimientoId;
    }

    /**
     * Precio pagado por cada presentación (S/ por saco), si se registró.
     */
    public function precioUnitario(): ?float
    {
        return $this->precioUnitario;
    }

    public function subtotal(): ?float
    {
        return $this->precioUnitario !== null ? round($this->precioUnitario * $this->cantidadPresentaciones, 2) : null;
    }

    /**
     * "2 × Saco 25 kg".
     */
    public function descripcionPresentacion(): string
    {
        $numero = static fn (float $v): string => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');

        return $numero($this->cantidadPresentaciones).' × '.$this->presentacionNombre.' '.$numero($this->presentacionCantidad).' '.$this->presentacionUnidad;
    }

    public function conCompraYMovimiento(int $compraInsumoId, int $insumoMovimientoId): self
    {
        return new self(
            id: $this->id,
            compraInsumoId: $compraInsumoId,
            insumoId: $this->insumoId,
            presentacionInsumoId: $this->presentacionInsumoId,
            presentacionNombre: $this->presentacionNombre,
            presentacionCantidad: $this->presentacionCantidad,
            presentacionUnidad: $this->presentacionUnidad,
            cantidadPresentaciones: $this->cantidadPresentaciones,
            cantidadBase: $this->cantidadBase,
            unidadBase: $this->unidadBase,
            insumoMovimientoId: $insumoMovimientoId,
            precioUnitario: $this->precioUnitario,
        );
    }
}
