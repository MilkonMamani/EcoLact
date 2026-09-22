<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\ProductoPrecio;
use App\Domain\Ventas\ProductoPrecioRepository;

final class ObtenerProductoPrecios
{
    public function __construct(
        private readonly ProductoPrecioRepository $precios,
    ) {}

    /**
     * @return ProductoPrecio[]
     */
    public function execute(int $productoId): array
    {
        return $this->precios->listarPorProducto($productoId);
    }
}
