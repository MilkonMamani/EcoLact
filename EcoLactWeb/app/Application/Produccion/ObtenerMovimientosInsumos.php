<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;

/**
 * Pantalla "Movimientos" de Insumos: historial completo de entradas/salidas
 * de todos los insumos (a diferencia del detalle de un insumo puntual, que
 * ya resuelve ObtenerDetalleStock).
 */
final class ObtenerMovimientosInsumos
{
    public function __construct(
        private readonly InsumoMovimientoRepository $movimientos,
    ) {}

    /**
     * @return InsumoMovimiento[]
     */
    public function execute(): array
    {
        return $this->movimientos->listar();
    }
}
