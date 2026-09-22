<?php

namespace App\Application\Proveedor;

use App\Domain\Proveedor\ProveedorZonaHistorial;
use App\Domain\Proveedor\ProveedorZonaHistorialRepository;

final class ObtenerZonaHistorialProveedor
{
    public function __construct(
        private readonly ProveedorZonaHistorialRepository $historiales,
    ) {}

    /**
     * @return ProveedorZonaHistorial[]
     */
    public function execute(int $proveedorId): array
    {
        return $this->historiales->listarPorProveedor($proveedorId);
    }
}
