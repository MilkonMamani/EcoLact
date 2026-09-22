<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\LoteProduccionInsumo;
use App\Domain\Produccion\LoteProduccionInsumoRepository;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;

class EloquentLoteProduccionInsumoRepository implements LoteProduccionInsumoRepository
{
    public function guardar(LoteProduccionInsumo $loteProduccionInsumo): LoteProduccionInsumo
    {
        $model = LoteProduccionInsumoModel::create($this->toModelData($loteProduccionInsumo));

        return $this->toEntity($model);
    }

    /**
     * @return LoteProduccionInsumo[]
     */
    public function listarPorLote(int $loteProduccionId): array
    {
        return LoteProduccionInsumoModel::where('lote_produccion_id', $loteProduccionId)
            ->orderBy('id')
            ->get()
            ->map(fn (LoteProduccionInsumoModel $model): LoteProduccionInsumo => $this->toEntity($model))
            ->all();
    }

    /**
     * @param  list<int>  $loteIds
     * @return array<int, float>
     */
    public function cantidadUsadaPorLote(int $insumoId, array $loteIds): array
    {
        if ($loteIds === []) {
            return [];
        }

        return LoteProduccionInsumoModel::query()
            ->where('insumo_id', $insumoId)
            ->whereIn('lote_produccion_id', $loteIds)
            ->selectRaw('lote_produccion_id, SUM(cantidad_usada) as total')
            ->groupBy('lote_produccion_id')
            ->pluck('total', 'lote_produccion_id')
            ->map(fn ($total): float => round((float) $total, 4))
            ->all();
    }

    private function toEntity(LoteProduccionInsumoModel $model): LoteProduccionInsumo
    {
        return new LoteProduccionInsumo(
            id: $model->id,
            loteProduccionId: $model->lote_produccion_id,
            insumoId: $model->insumo_id,
            cantidadUsada: (float) $model->cantidad_usada,
            cantidadRequerida: (float) $model->cantidad_requerida,
            unidadBase: $model->unidad_base,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(LoteProduccionInsumo $loteProduccionInsumo): array
    {
        return [
            'lote_produccion_id' => $loteProduccionInsumo->loteProduccionId(),
            'insumo_id' => $loteProduccionInsumo->insumoId(),
            'cantidad_requerida' => $loteProduccionInsumo->cantidadRequerida(),
            'cantidad_usada' => $loteProduccionInsumo->cantidadUsada(),
            'unidad_base' => $loteProduccionInsumo->unidadBase(),
        ];
    }
}
