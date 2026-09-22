<?php

namespace App\Application\Proveedor;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;

final class ObtenerProveedor
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
    ) {}

    public function execute(int $proveedorId): ?Proveedor
    {
        return $this->proveedores->buscarPorId($proveedorId);
    }
}
