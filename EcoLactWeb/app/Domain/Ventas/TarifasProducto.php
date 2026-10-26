<?php

namespace App\Domain\Ventas;

/**
 * Los precios vigentes de un producto (los que fija INVENTARIO -> Productos)
 * y la ÚNICA regla que decide cuál se cobra en una venta:
 *
 *   ¿compra un PROVEEDOR registrado?  → precio PROVEEDOR
 *   si no, cantidad ≥ mínimo mayorista → precio MAYORISTA
 *   si no                              → precio PUBLICO
 *
 * El comprador registrado no fija el precio: lo que hace mayorista una venta
 * es la cantidad. Un precio MAYORISTA sin cantidad mínima no se alcanza nunca.
 */
final readonly class TarifasProducto
{
    public function __construct(
        public ?ProductoPrecio $publico,
        public ?ProductoPrecio $mayorista,
        public ?ProductoPrecio $proveedor,
    ) {}

    public function minimoMayorista(): ?float
    {
        return $this->mayorista?->cantidadMinima();
    }

    public function aplicable(bool $esProveedor, float $cantidad): ?ProductoPrecio
    {
        if ($esProveedor) {
            return $this->proveedor;
        }

        $minimo = $this->minimoMayorista();

        if ($this->mayorista !== null && $minimo !== null && $cantidad >= $minimo) {
            return $this->mayorista;
        }

        return $this->publico;
    }

    /** Hay al menos un precio con el que se puede vender el producto. */
    public function sePuedeVender(): bool
    {
        return $this->publico !== null || $this->proveedor !== null || $this->minimoMayorista() !== null;
    }
}
