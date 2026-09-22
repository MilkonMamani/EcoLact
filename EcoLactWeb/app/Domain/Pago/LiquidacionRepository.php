<?php

namespace App\Domain\Pago;

interface LiquidacionRepository
{
    public function guardar(Liquidacion $liquidacion): Liquidacion;

    public function actualizar(Liquidacion $liquidacion): Liquidacion;

    public function buscarPorId(int $id): ?Liquidacion;

    /**
     * @return Liquidacion[]
     */
    public function listarPorProveedor(int $proveedorId): array;

    /** @param int[] $valorizacionIds @return int[] */
    public function listarValorizacionIdsIncluidas(array $valorizacionIds): array;

    /**
     * @return Liquidacion[]
     */
    public function listarPorPeriodo(int $periodoId): array;

    /**
     * @return Liquidacion[]
     */
    public function listar(): array;
}
