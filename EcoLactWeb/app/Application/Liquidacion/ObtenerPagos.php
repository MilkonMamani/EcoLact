<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\PagoProveedor;
use App\Domain\Pago\PagoProveedorRepository;

final class ObtenerPagos
{
    public function __construct(
        private readonly PagoProveedorRepository $pagos,
    ) {}

    /**
     * Con $proveedorId, acota a los pagos de ese proveedor (uso de
     * PROVEEDOR, siempre con su propio id); sin él, lista todos (uso de
     * ADMINISTRADOR).
     *
     * @return PagoProveedor[]
     */
    public function execute(?int $proveedorId = null): array
    {
        return $proveedorId !== null
            ? $this->pagos->listarPorProveedor($proveedorId)
            : $this->pagos->listar();
    }
}
