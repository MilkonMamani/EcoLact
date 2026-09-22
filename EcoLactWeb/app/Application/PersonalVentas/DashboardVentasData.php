<?php

namespace App\Application\PersonalVentas;

use App\Domain\Comunicacion\Notificacion;
use App\Domain\Ventas\VentaProducto;

final readonly class DashboardVentasData
{
    /**
     * @param  array{ventas: int, totalVendido: float, productosVendidos: float, ticketPromedio: ?float}  $kpis
     * @param  list<VentaProducto>  $ventasRecientes
     * @param  list<array{producto: string, stock: float, unidad: string}>  $stockCritico
     * @param  list<array{producto: string, cantidad: float}>  $masVendidos
     * @param  list<Notificacion>  $notificacionesRecientes
     */
    public function __construct(
        public array $kpis,
        public array $ventasRecientes,
        public array $stockCritico,
        public array $masVendidos,
        public array $notificacionesRecientes,
    ) {}
}
