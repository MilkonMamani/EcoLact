<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\LiquidacionRepository;

/**
 * Compone el resumen de "Mis liquidaciones" del proveedor: última
 * liquidación, monto por cobrar (saldo pendiente positivo entre todas sus
 * liquidaciones) y monto ya pagado. Reutiliza CalcularPago (mismo cálculo
 * que ya usa ObtenerDetalleLiquidacion) en vez de sumar pagos_proveedor por
 * separado, para no duplicar la regla de "pagos anulados no cuentan".
 */
final class ObtenerResumenLiquidacionesProveedor
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly CalcularPago $calcularPago,
    ) {}

    public function execute(int $proveedorId): ResumenLiquidacionesProveedorData
    {
        $liquidaciones = $this->liquidaciones->listarPorProveedor($proveedorId);

        // Desempata por id cuando dos liquidaciones se calcularon en el
        // mismo instante (fechaCalculo solo tiene precisión de segundo en
        // la BD): un id mayor siempre corresponde a una liquidación
        // generada después.
        usort($liquidaciones, static fn ($a, $b): int => [$b->fechaCalculo(), $b->id()] <=> [$a->fechaCalculo(), $a->id()]);

        $porCobrar = 0.0;
        $pagado = 0.0;

        foreach ($liquidaciones as $liquidacion) {
            $saldo = $this->calcularPago->execute($liquidacion->id());
            $porCobrar += max(0.0, $saldo);
            $pagado += $liquidacion->totalPagar() - max(0.0, $saldo);
        }

        return new ResumenLiquidacionesProveedorData(
            ultimaLiquidacion: $liquidaciones[0] ?? null,
            porCobrar: round($porCobrar, 2),
            pagado: round($pagado, 2),
            totalLiquidaciones: count($liquidaciones),
        );
    }
}
