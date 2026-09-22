<?php

namespace App\Application\Ventas;

use App\Domain\Produccion\Producto;
use App\Domain\Produccion\ProductoRepository;

final class ObtenerProductosActivos
{
    public function __construct(
        private readonly ProductoRepository $productos,
    ) {}

    /**
     * @return Producto[]
     */
    public function execute(): array
    {
        return $this->productos->listarActivos();
    }
}
