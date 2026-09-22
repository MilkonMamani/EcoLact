<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\PagoProveedor;
use App\Domain\Pago\PagoProveedorRepository;

final class ObtenerPago
{
    public function __construct(
        private readonly PagoProveedorRepository $pagos,
    ) {}

    public function execute(int $pagoId): ?PagoProveedor
    {
        return $this->pagos->buscarPorId($pagoId);
    }
}
