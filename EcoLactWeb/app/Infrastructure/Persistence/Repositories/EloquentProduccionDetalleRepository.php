<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\ProduccionDetalle;
use App\Domain\Produccion\ProduccionDetalleRepository;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;

class EloquentProduccionDetalleRepository implements ProduccionDetalleRepository
{
    public function guardar(ProduccionDetalle $detalle): ProduccionDetalle
    {
        $model = ProduccionDetalleModel::create($this->toModelData($detalle));

        return $this->toEntity($model);
    }

    public function actualizar(ProduccionDetalle $detalle): ProduccionDetalle
    {
        $model = ProduccionDetalleModel::findOrFail($detalle->id());
        $model->fill($this->toModelData($detalle));
        $model->save();

        return $this->toEntity($model);
    }

    /**
     * @return ProduccionDetalle[]
     */
    public function listarPorLote(int $loteProduccionId): array
    {
        return ProduccionDetalleModel::where('lote_produccion_id', $loteProduccionId)
            ->orderBy('id')
            ->get()
            ->map(fn (ProduccionDetalleModel $model): ProduccionDetalle => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProduccionDetalleModel $model): ProduccionDetalle
    {
        return new ProduccionDetalle(
            id: $model->id,
            loteProduccionId: $model->lote_produccion_id,
            productoId: $model->producto_id,
            cantidadPlanificada: (float) $model->cantidad_planificada,
            cantidadObtenida: $model->cantidad_obtenida !== null ? (float) $model->cantidad_obtenida : null,
            recetaId: $model->receta_id,
            recetaVersion: $model->receta_version,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ProduccionDetalle $detalle): array
    {
        return [
            'lote_produccion_id' => $detalle->loteProduccionId(),
            'producto_id' => $detalle->productoId(),
            'receta_id' => $detalle->recetaId(),
            'receta_version' => $detalle->recetaVersion(),
            'cantidad_planificada' => $detalle->cantidadPlanificada(),
            'cantidad_obtenida' => $detalle->cantidadObtenida(),
        ];
    }
}
