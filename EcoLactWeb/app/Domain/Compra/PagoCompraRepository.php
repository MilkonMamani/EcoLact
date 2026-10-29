<?php

namespace App\Domain\Compra;

interface PagoCompraRepository
{
    public function guardar(PagoCompra $pago): PagoCompra;

    public function enlazarMovimientoCaja(int $pagoId, int $movimientoCajaId): void;

    /**
     * @return list<PagoCompra> del más antiguo al más reciente
     */
    public function listarPorCompra(int $compraId): array;

    public function totalPagado(int $compraId): float;
}
