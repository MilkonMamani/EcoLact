<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\AjusteLiquidacion;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\PagoProveedor;
use App\Domain\Ventas\VentaProducto;

/**
 * @phpstan-type EntregaFila array{fecha: string, litros: float, resultado: ?string, pagables: float}
 * @phpstan-type IncidenciaInfo array{fecha: string, resultado: string, entregaId: int}
 */
final readonly class LiquidacionDetalleData
{
    /**
     * @param  list<EntregaFila>  $entregas
     * @param  IncidenciaInfo|null  $incidencia
     * @param  VentaProducto[]  $compras
     * @param  AjusteLiquidacion[]  $ajustes
     * @param  PagoProveedor[]  $pagos
     */
    public function __construct(
        public Liquidacion $liquidacion,
        public string $proveedorNombre,
        public string $proveedorDni,
        public array $entregas,
        public ?array $incidencia,
        public array $compras,
        public array $ajustes,
        public array $pagos,
        public float $saldoPendiente,
    ) {}
}
