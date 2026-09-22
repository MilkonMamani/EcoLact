<?php

namespace App\Domain\Produccion;

interface LoteProduccionInsumoRepository
{
    public function guardar(LoteProduccionInsumo $loteProduccionInsumo): LoteProduccionInsumo;

    /**
     * @return LoteProduccionInsumo[]
     */
    public function listarPorLote(int $loteProduccionId): array;

    /**
     * Cantidad consumida de un insumo (ej. la leche) por cada lote indicado,
     * indexada por id de lote. Los lotes sin consumo de ese insumo no aparecen.
     *
     * @param  list<int>  $loteIds
     * @return array<int, float>
     */
    public function cantidadUsadaPorLote(int $insumoId, array $loteIds): array;
}
