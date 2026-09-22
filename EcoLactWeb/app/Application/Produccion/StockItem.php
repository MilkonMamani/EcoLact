<?php

namespace App\Application\Produccion;

/**
 * Fila de solo lectura para la vista unificada de Stock: combina
 * producto_stock (vía Producto) e Insumo en una sola tabla, sin mezclar sus
 * Domain entities (Producto/ProductoStock/Insumo siguen siendo aggregates
 * separados; esto es un read model para la pantalla, no una entidad).
 */
final class StockItem
{
    public function __construct(
        private readonly string $tipo,
        private readonly int $id,
        private readonly string $nombre,
        private readonly string $unidadMedida,
        private readonly float $cantidadActual,
        private readonly ?float $stockMinimo,
        private readonly string $estado,
    ) {}

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function unidadMedida(): string
    {
        return $this->unidadMedida;
    }

    public function cantidadActual(): float
    {
        return $this->cantidadActual;
    }

    public function stockMinimo(): ?float
    {
        return $this->stockMinimo;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function sinStock(): bool
    {
        return $this->cantidadActual <= 0.0;
    }

    public function stockBajo(): bool
    {
        return $this->stockMinimo !== null && $this->cantidadActual < $this->stockMinimo;
    }
}
