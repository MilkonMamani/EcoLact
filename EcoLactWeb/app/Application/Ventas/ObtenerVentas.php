<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\VentaProducto;
use App\Domain\Ventas\VentaProductoRepository;

/**
 * Listado general de ventas (todos los proveedores/compradores/público),
 * a diferencia de ObtenerVentasProveedor que acota a un único proveedor.
 */
final class ObtenerVentas
{
    public function __construct(
        private readonly VentaProductoRepository $ventas,
    ) {}

    /**
     * @return VentaProducto[]
     */
    public function execute(): array
    {
        return $this->ventas->listar();
    }
}
