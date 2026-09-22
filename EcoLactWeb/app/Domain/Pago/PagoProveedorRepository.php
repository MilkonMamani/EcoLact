<?php

namespace App\Domain\Pago;

interface PagoProveedorRepository
{
    public function guardar(PagoProveedor $pago): PagoProveedor;

    public function actualizar(PagoProveedor $pago): PagoProveedor;

    public function buscarPorId(int $id): ?PagoProveedor;

    /**
     * @return PagoProveedor[]
     */
    public function listarPorLiquidacion(int $liquidacionId): array;

    /**
     * Pagos de todas las liquidaciones de un proveedor (join a través de
     * liquidaciones: pagos_proveedor no tiene proveedor_id propio).
     *
     * @return PagoProveedor[]
     */
    public function listarPorProveedor(int $proveedorId): array;

    /**
     * @return PagoProveedor[]
     */
    public function listar(): array;
}
