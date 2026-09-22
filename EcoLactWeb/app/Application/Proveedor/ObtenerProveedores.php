<?php

namespace App\Application\Proveedor;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;

final class ObtenerProveedores
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
    ) {}

    /**
     * Con $zonaId, acota a los proveedores de esa zona (uso de ACOPIADOR,
     * siempre con su propia zona); sin él, lista todos (uso de
     * ADMINISTRADOR).
     *
     * @return Proveedor[]
     */
    public function execute(?int $zonaId = null): array
    {
        return $zonaId !== null
            ? $this->proveedores->listarPorZona($zonaId)
            : $this->proveedores->listar();
    }
}
