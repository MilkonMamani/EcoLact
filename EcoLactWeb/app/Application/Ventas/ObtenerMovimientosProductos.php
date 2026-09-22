<?php

namespace App\Application\Ventas;

use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;

/**
 * Pantalla "Movimientos" de Productos: historial completo de entradas
 * (producción) y salidas (ventas) de todos los productos, a diferencia del
 * detalle de un producto puntual, que ya resuelve ObtenerDetalleStock.
 */
final class ObtenerMovimientosProductos
{
    public function __construct(
        private readonly ProductoMovimientoRepository $movimientos,
    ) {}

    /**
     * @return ProductoMovimiento[]
     */
    public function execute(): array
    {
        return $this->movimientos->listar();
    }
}
