<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\DetalleEntregaCalculado;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;

/**
 * Persistencia de las liquidaciones semanales ABIERTAS de proveedor calculadas
 * por entregas: PENDIENTE (por pagar) o NO_PAGABLE (semana RECHAZADA, S/ 0.00).
 * Ambas se recalculan desde las entregas; nunca modifica un pago PAGADO.
 */
interface PagosPendientesProveedorStore
{
    /** @return list<string> Inicios canónicos de semanas cerradas por un pago o liquidación previa. */
    public function semanasCerradas(int $proveedorId): array;

    /**
     * Suelta las entregas de los pendientes del proveedor (para recalcularlas) y
     * devuelve los pendientes existentes como [inicio del periodo (Y-m-d) => id].
     *
     * @return array<string, int>
     */
    public function liberarPendientes(int $proveedorId): array;

    /**
     * Crea o actualiza la liquidación abierta de un proveedor y periodo, con el
     * detalle de sus entregas, en el estado indicado (PENDIENTE o NO_PAGABLE).
     * Devuelve true si la creó y false si actualizó una existente.
     *
     * @param  array<string, mixed>  $baseCalculo
     * @param  list<DetalleEntregaCalculado>  $detalles
     */
    public function guardarPendiente(?int $existenteId, int $proveedorId, string $nombre, ?int $configuracionId, PeriodoPago $periodo, float $monto, array $baseCalculo, array $detalles, ?int $actorUserId, EstadoObligacionPago $estado = EstadoObligacionPago::PENDIENTE): bool;

    /** @param int[] $ids */
    public function eliminarPendientes(array $ids): void;

    /**
     * Descuentos de planilla (compras de producto a cuenta del pago) del proveedor
     * en la semana, aún no aplicados a un pago realizado. Una compra cuya semana
     * ya está pagada o no tiene pago ($semanasSinPago, p. ej. RECHAZADA) pasa a la
     * siguiente semana pagable.
     *
     * @param  list<string>  $semanasSinPago  inicios canónicos (Y-m-d)
     * @return list<array{venta_id: int, importe: float}>
     */
    public function descuentosDisponibles(int $proveedorId, PeriodoPago $periodo, array $semanasSinPago = []): array;
}
