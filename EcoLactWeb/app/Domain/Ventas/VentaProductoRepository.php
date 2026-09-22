<?php

namespace App\Domain\Ventas;

interface VentaProductoRepository
{
    public function guardar(VentaProducto $venta): VentaProducto;

    public function buscarPorId(int $id): ?VentaProducto;

    /**
     * @return VentaProducto[]
     */
    public function listarPorProveedorYPeriodo(int $proveedorId, int $periodoId, ?string $formaPago = null): array;

    /**
     * @return VentaProducto[]
     */
    public function listarPorProveedor(int $proveedorId): array;

    /**
     * @return VentaProducto[]
     */
    public function listar(): array;
}
