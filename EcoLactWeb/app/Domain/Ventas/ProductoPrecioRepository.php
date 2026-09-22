<?php

namespace App\Domain\Ventas;

use DateTimeImmutable;

interface ProductoPrecioRepository
{
    public function guardar(ProductoPrecio $precio): ProductoPrecio;

    public function buscarVigente(int $productoId, string $tipoCliente, DateTimeImmutable $fecha): ?ProductoPrecio;

    /**
     * @return ProductoPrecio[]
     */
    public function listarPorProducto(int $productoId): array;
}
