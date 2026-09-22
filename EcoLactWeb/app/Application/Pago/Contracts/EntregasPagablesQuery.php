<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\EntregaPagable;

interface EntregasPagablesQuery
{
    /**
     * Entregas no anuladas que aún pueden formar parte de un pago pendiente: las
     * que no están en ningún pago, y las que ya están en un pago PENDIENTE (para
     * recalcularlo). Quedan fuera las de pagos PAGADOS (inmutables) y las de una
     * liquidación anterior vigente.
     *
     * @return EntregaPagable[]
     */
    public function porAsignar(?int $proveedorId = null): array;

    /** ¿La entrega ya forma parte de un pago realizado (PAGADO) o de una liquidación anterior? */
    public function estaEnPagoRealizado(int $entregaId): bool;

    /** @return int[] proveedores con algún pago pendiente calculado por entregas */
    public function proveedoresConPendientes(): array;
}
