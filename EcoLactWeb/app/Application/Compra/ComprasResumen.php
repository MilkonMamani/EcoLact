<?php

namespace App\Application\Compra;

/**
 * Indicadores de Compras y gastos para los filtros aplicados. `comprado` es
 * lo adquirido (total de las compras); `pagado` es lo que de verdad salió de
 * Caja por ellas; `pendiente` es la diferencia que aún se debe.
 */
final readonly class ComprasResumen
{
    public function __construct(
        public int $compras,
        public float $comprado,
        public float $pagado,
        public float $pendiente,
    ) {}
}
