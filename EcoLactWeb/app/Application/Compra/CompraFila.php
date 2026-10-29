<?php

namespace App\Application\Compra;

use App\Domain\Shared\Enums\EstadoPagoCompra;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use DateTimeImmutable;

/**
 * Una compra tal como la pinta el historial de Compras y gastos.
 *
 * `destino`: INVENTARIO (todo entra al stock), GASTO (nada entra) o MIXTO.
 * `articulo`: su primera línea resumida ("Cuajo · 8 baldes"); `articulosAdicionales`
 * cuántas líneas más tiene.
 */
final readonly class CompraFila
{
    public function __construct(
        public int $id,
        public string $codigo,
        public DateTimeImmutable $fecha,
        public string $concepto,
        public ?string $proveedor,
        public ?string $comprobante,
        public ?string $categoria,
        public string $destino,
        public ?float $total,
        public float $pagado,
        public EstadoPagoCompra $estadoPago,
        public EstadoRecepcionCompra $estadoRecepcion,
        public ?string $articulo = null,
        public int $articulosAdicionales = 0,
    ) {}

    /** Lo que se compró: los artículos reales; el concepto solo si no hay líneas. */
    public function descripcion(): string
    {
        if ($this->articulo === null) {
            return $this->concepto;
        }

        return $this->articulo.match ($this->articulosAdicionales) {
            0 => '',
            1 => ' y 1 artículo más',
            default => " y {$this->articulosAdicionales} artículos más",
        };
    }

    public function saldo(): float
    {
        return $this->total !== null ? max(0.0, round($this->total - $this->pagado, 2)) : 0.0;
    }

    public function destinoEtiqueta(): string
    {
        return match ($this->destino) {
            'INVENTARIO' => 'Inventario',
            'MIXTO' => 'Inventario y gasto',
            default => 'Gasto',
        };
    }
}
