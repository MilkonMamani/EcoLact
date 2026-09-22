<?php

namespace App\Application\Proveedor;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Comunicacion\Comunicado;
use App\Domain\Entrega\Entrega;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\PeriodoLiquidacion;
use App\Domain\Ventas\VentaProducto;

final readonly class DashboardProveedorData
{
    /**
     * @param  array{entregas: int, litros: float}  $kpis
     * @param  list<Entrega>  $ultimasEntregas
     * @param  array<int, ?string>  $resultadosPorEntrega
     * @param  list<VentaProducto>  $comprasRecientes
     * @param  list<Comunicado>  $comunicadosRecientes
     */
    public function __construct(
        public array $kpis,
        public ?AnalisisCalidad $ultimoAnalisis,
        public ?Liquidacion $ultimaLiquidacion,
        public ?PeriodoLiquidacion $ultimaLiquidacionPeriodo,
        public float $porCobrar,
        public array $ultimasEntregas,
        public array $resultadosPorEntrega,
        public array $comprasRecientes,
        public array $comunicadosRecientes,
    ) {}
}
