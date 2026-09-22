<?php

namespace App\Domain\Produccion;

interface ProductoMovimientoRepository
{
    public function guardar(ProductoMovimiento $movimiento): ProductoMovimiento;

    /**
     * @return ProductoMovimiento[]
     */
    public function listarPorProducto(int $productoId): array;

    /**
     * @return ProductoMovimiento[]
     */
    public function listar(): array;
}
