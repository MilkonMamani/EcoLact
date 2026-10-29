<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use DateTimeImmutable;

interface CompraInsumoRepository
{
    /** Crea la compra y le asigna su código "C-0001". */
    public function guardar(CompraInsumo $compra): CompraInsumo;

    public function guardarDetalle(CompraInsumoDetalle $detalle): CompraInsumoDetalle;

    public function buscarPorId(int $id): ?CompraInsumo;

    /**
     * La compra bloqueada hasta el fin de la transacción (SELECT ... FOR
     * UPDATE): dos pagos o dos recepciones simultáneas se ejecutan en fila.
     */
    public function buscarParaActualizar(int $id): ?CompraInsumo;

    /**
     * @return list<CompraInsumoDetalle>
     */
    public function listarDetalles(int $compraId): array;

    /**
     * Compras por id, en una consulta (para enlazar movimientos con su boleta).
     *
     * @param  list<int>  $ids
     * @return array<int, CompraInsumo>
     */
    public function buscarVarias(array $ids): array;

    /**
     * Fecha de la compra más reciente de cada insumo comprado alguna vez.
     *
     * @return array<int, DateTimeImmutable> insumo_id => fecha
     */
    public function ultimaCompraPorInsumo(): array;

    public function enlazarMovimientoDetalle(int $detalleId, int $insumoMovimientoId): void;

    public function actualizarRecepcion(int $compraId, EstadoRecepcionCompra $estado, ?DateTimeImmutable $recibidaEn, ?int $recibidaPorUserId): void;

    /** Guarda lo pagado y el estado de pago que resulta (lo decide CompraInsumo). */
    public function actualizarPagos(CompraInsumo $compra, float $montoPagado): void;
}
