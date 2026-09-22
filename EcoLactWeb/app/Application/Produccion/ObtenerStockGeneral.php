<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;

/**
 * Vista unificada de inventario (productos terminados + insumos) en una
 * sola tabla, tal como la pide el módulo Stock: "Producto ≠ Stock ≠ Precio
 * ≠ Venta ≠ Producción", pero la pantalla de Stock sí necesita mostrarlos
 * juntos como punto de consulta único.
 */
final class ObtenerStockGeneral
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoStockRepository $productoStock,
        private readonly InsumoRepository $insumos,
    ) {}

    /**
     * @return StockItem[]
     */
    public function execute(): array
    {
        $stockPorProductoId = [];

        foreach ($this->productoStock->listarTodos() as $stock) {
            $stockPorProductoId[$stock->productoId()] = $stock->cantidadActual();
        }

        $items = [];

        foreach ($this->productos->listar() as $producto) {
            $items[] = new StockItem(
                tipo: 'PRODUCTO',
                id: $producto->id(),
                nombre: $producto->nombre(),
                unidadMedida: $producto->unidadMedida(),
                cantidadActual: $stockPorProductoId[$producto->id()] ?? 0.0,
                stockMinimo: null,
                estado: $producto->estado(),
            );
        }

        foreach ($this->insumos->listar() as $insumo) {
            $items[] = new StockItem(
                tipo: 'INSUMO',
                id: $insumo->id(),
                nombre: $insumo->nombre(),
                unidadMedida: $insumo->unidadBase(),
                cantidadActual: $insumo->stockActual(),
                stockMinimo: $insumo->stockMinimo(),
                estado: $insumo->estado(),
            );
        }

        usort($items, fn (StockItem $a, StockItem $b): int => $a->nombre() <=> $b->nombre());

        return $items;
    }
}
