<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\TratamientoCompra;
use InvalidArgumentException;

/**
 * Línea de una compra. Según su tratamiento:
 *
 *   INVENTARIO  cuántas presentaciones de un insumo se compraron y su total en
 *               la unidad base. Guarda una fotografía de la presentación usada
 *               (nombre, contenido y unidad) para que la compra histórica no
 *               cambie si la presentación se edita después. Tiene su
 *               movimiento de stock cuando la compra se recibe.
 *   CONSUMO, BIEN, SERVICIO  un concepto libre ("Gaseosa 3 L", "Luz de octubre")
 *               con cantidad, unidad y precio; nunca toca el stock.
 */
final class CompraInsumoDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $compraInsumoId,
        private readonly ?int $insumoId,
        private readonly ?int $presentacionInsumoId,
        private readonly ?string $presentacionNombre,
        private readonly ?float $presentacionCantidad,
        private readonly ?string $presentacionUnidad,
        private readonly float $cantidadPresentaciones,
        private readonly ?float $cantidadBase,
        private readonly ?string $unidadBase,
        private readonly ?int $insumoMovimientoId = null,
        private readonly ?float $precioUnitario = null,
        private readonly TratamientoCompra $tratamiento = TratamientoCompra::INVENTARIO,
        private readonly ?string $descripcion = null,
        private readonly ?string $unidad = null,
    ) {
        if ($cantidadPresentaciones <= 0.0) {
            throw new InvalidArgumentException('La cantidad comprada debe ser mayor que cero.');
        }

        if ($precioUnitario !== null && $precioUnitario < 0.0) {
            throw new InvalidArgumentException('El precio unitario no puede ser negativo.');
        }

        if ($tratamiento->generaStock()) {
            if ($insumoId === null || $presentacionNombre === null || $presentacionCantidad === null || $presentacionUnidad === null || $unidadBase === null || ($cantidadBase ?? 0.0) <= 0.0) {
                throw new InvalidArgumentException('Una línea de inventario necesita su insumo, su presentación y la cantidad en unidad base.');
            }
        } elseif ($descripcion === null || trim($descripcion) === '') {
            throw new InvalidArgumentException('Describe qué se compró en cada línea.');
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

    public function insumoId(): ?int
    {
        return $this->insumoId;
    }

    public function presentacionInsumoId(): ?int
    {
        return $this->presentacionInsumoId;
    }

    public function presentacionNombre(): ?string
    {
        return $this->presentacionNombre;
    }

    public function presentacionCantidad(): ?float
    {
        return $this->presentacionCantidad;
    }

    public function presentacionUnidad(): ?string
    {
        return $this->presentacionUnidad;
    }

    /** Cantidad comprada: presentaciones en inventario, unidades del concepto en el resto. */
    public function cantidadPresentaciones(): float
    {
        return $this->cantidadPresentaciones;
    }

    public function cantidadBase(): ?float
    {
        return $this->cantidadBase;
    }

    public function unidadBase(): ?string
    {
        return $this->unidadBase;
    }

    public function insumoMovimientoId(): ?int
    {
        return $this->insumoMovimientoId;
    }

    public function tratamiento(): TratamientoCompra
    {
        return $this->tratamiento;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function unidad(): ?string
    {
        return $this->unidad;
    }

    public function generaStock(): bool
    {
        return $this->tratamiento->generaStock();
    }

    /**
     * Precio pagado por cada presentación o unidad (S/ por saco), si se registró.
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
     * "2 × Saco 25 kg" (inventario) o "3 und" (gasto).
     */
    public function descripcionPresentacion(): string
    {
        $numero = static fn (float $v): string => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');

        if (! $this->generaStock()) {
            return $numero($this->cantidadPresentaciones).' '.($this->unidad ?? 'und');
        }

        return $numero($this->cantidadPresentaciones).' × '.$this->presentacionNombre.' '.$numero((float) $this->presentacionCantidad).' '.$this->presentacionUnidad;
    }

    public function conCompra(int $compraInsumoId): self
    {
        return $this->con(compraInsumoId: $compraInsumoId, insumoMovimientoId: $this->insumoMovimientoId);
    }

    public function conCompraYMovimiento(int $compraInsumoId, int $insumoMovimientoId): self
    {
        return $this->con(compraInsumoId: $compraInsumoId, insumoMovimientoId: $insumoMovimientoId);
    }

    private function con(int $compraInsumoId, ?int $insumoMovimientoId): self
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
            tratamiento: $this->tratamiento,
            descripcion: $this->descripcion,
            unidad: $this->unidad,
        );
    }
}
