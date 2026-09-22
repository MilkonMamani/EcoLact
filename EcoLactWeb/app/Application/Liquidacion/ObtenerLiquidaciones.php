<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\LiquidacionRepository;

final class ObtenerLiquidaciones
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
    ) {}

    /**
     * Con $proveedorId, acota a las liquidaciones de ese proveedor (uso
     * del propio PROVEEDOR); sin él, lista todas (uso de ADMINISTRADOR).
     *
     * @return Liquidacion[]
     */
    public function execute(?int $proveedorId = null): array
    {
        return $proveedorId !== null
            ? $this->liquidaciones->listarPorProveedor($proveedorId)
            : $this->liquidaciones->listar();
    }
}
