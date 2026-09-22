<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\VentaProducto;
use App\Domain\Ventas\VentaProductoRepository;

final class ObtenerVentasProveedor
{
    public function __construct(
        private readonly VentaProductoRepository $ventas,
    ) {}

    /**
     * @return VentaProducto[]
     */
    public function execute(int $proveedorId): array
    {
        return $this->ventas->listarPorProveedor($proveedorId);
    }
}
