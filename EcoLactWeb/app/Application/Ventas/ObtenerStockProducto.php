<?php

namespace App\Application\Ventas;

use App\Domain\Produccion\ProductoStock;
use App\Domain\Produccion\ProductoStockRepository;

final class ObtenerStockProducto
{
    public function __construct(
        private readonly ProductoStockRepository $stock,
    ) {}

    public function execute(int $productoId): ?ProductoStock
    {
        return $this->stock->buscarPorProductoId($productoId);
    }
}
