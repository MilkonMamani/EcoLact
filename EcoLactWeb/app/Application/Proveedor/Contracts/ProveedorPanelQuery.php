<?php

namespace App\Application\Proveedor\Contracts;

use App\Application\Proveedor\ProveedorPanelData;

interface ProveedorPanelQuery
{
    /** Todo lo que muestra el panel lateral de un proveedor; null si no existe. */
    public function obtener(int $proveedorId): ?ProveedorPanelData;
}
