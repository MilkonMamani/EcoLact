<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PagoSemanalProveedoresQuery;

/** Cantidad de pagos registrados de un proveedor y cuántos siguen pendientes. */
final class ObtenerResumenPagosProveedor
{
    public function __construct(private readonly PagoSemanalProveedoresQuery $consulta) {}

    /**
     * @return array{total: int, pendientes: int}
     */
    public function execute(int $proveedorId): array
    {
        return $this->consulta->resumenProveedor($proveedorId);
    }
}
