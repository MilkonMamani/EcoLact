<?php

namespace App\Domain\Produccion;

interface CompraInsumoRepository
{
    public function guardar(CompraInsumo $compra): CompraInsumo;

    public function guardarDetalle(CompraInsumoDetalle $detalle): CompraInsumoDetalle;

    public function buscarPorId(int $id): ?CompraInsumo;

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
     * @return array<int, \DateTimeImmutable> insumo_id => fecha
     */
    public function ultimaCompraPorInsumo(): array;
}
