<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PagoProveedorRepository;
use App\Domain\Shared\Enums\EstadoPago;
use DomainException;

/**
 * Calcula el saldo pendiente de pago de una liquidación: total a pagar
 * menos la suma de los pagos ya registrados (sin contar los ANULADOs).
 */
final class CalcularPago
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly PagoProveedorRepository $pagos,
    ) {}

    public function execute(int $liquidacionId): float
    {
        $liquidacion = $this->liquidaciones->buscarPorId($liquidacionId);

        if ($liquidacion === null) {
            throw new DomainException('La liquidación indicada no existe.');
        }

        $totalPagado = 0.0;

        foreach ($this->pagos->listarPorLiquidacion($liquidacionId) as $pago) {
            if ($pago->estado() === EstadoPago::ANULADO) {
                continue;
            }

            $totalPagado += $pago->monto();
        }

        return round($liquidacion->totalPagar() - $totalPagado, 2);
    }
}
