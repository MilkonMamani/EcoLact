<?php

namespace App\Domain\Produccion;

interface PresentacionInsumoRepository
{
    public function guardar(PresentacionInsumo $presentacion): PresentacionInsumo;

    public function actualizar(PresentacionInsumo $presentacion): PresentacionInsumo;

    public function buscarPorId(int $id): ?PresentacionInsumo;

    /**
     * @return list<PresentacionInsumo>
     */
    public function listarPorInsumo(int $insumoId): array;

    /**
     * Presentaciones ACTIVAS de todos los insumos, agrupadas por insumo, en una consulta.
     *
     * @return array<int, list<PresentacionInsumo>>
     */
    public function listarActivasAgrupadas(): array;
}
