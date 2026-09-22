<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\DetalleEntregaCalculado;
use App\Domain\Pago\PeriodoPago;

/**
 * Persistencia de los pagos PENDIENTES de proveedor calculados por entregas.
 * Nunca modifica un pago PAGADO.
 */
interface PagosPendientesProveedorStore
{
    /**
     * Suelta las entregas de los pendientes del proveedor (para recalcularlas) y
     * devuelve los pendientes existentes como [inicio del periodo (Y-m-d) => id].
     *
     * @return array<string, int>
     */
    public function liberarPendientes(int $proveedorId): array;

    /**
     * Crea o actualiza el pago pendiente de un proveedor y periodo, con el detalle
     * de sus entregas. Devuelve true si lo creó y false si actualizó uno existente.
     *
     * @param  array<string, mixed>  $baseCalculo
     * @param  list<DetalleEntregaCalculado>  $detalles
     */
    public function guardarPendiente(?int $existenteId, int $proveedorId, string $nombre, ?int $configuracionId, PeriodoPago $periodo, float $monto, array $baseCalculo, array $detalles, ?int $actorUserId): bool;

    /** @param int[] $ids */
    public function eliminarPendientes(array $ids): void;

    /**
     * Descuentos de planilla (compras de producto a cuenta del pago) del proveedor
     * en la semana, aún no aplicados a un pago realizado.
     *
     * @return list<array{venta_id: int, importe: float}>
     */
    public function descuentosDisponibles(int $proveedorId, PeriodoPago $periodo): array;
}
