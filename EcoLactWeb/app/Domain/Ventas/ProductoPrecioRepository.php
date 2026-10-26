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

    /**
     * Cierra (INACTIVO, fecha_fin = $fecha) los precios ACTIVO del producto
     * para ese tipo de cliente. Se usa antes de registrar uno nuevo y al
     * retirar un precio: nunca se borran filas, las ventas pasadas las citan.
     */
    public function cerrarVigentes(int $productoId, string $tipoCliente, DateTimeImmutable $fecha): void;
}
