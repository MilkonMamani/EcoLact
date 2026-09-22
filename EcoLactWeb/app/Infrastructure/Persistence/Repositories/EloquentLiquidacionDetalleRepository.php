<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\LiquidacionDetalle;
use App\Domain\Pago\LiquidacionDetalleRepository;
use App\Infrastructure\Persistence\Models\LiquidacionDetalleModel;

class EloquentLiquidacionDetalleRepository implements LiquidacionDetalleRepository
{
    public function guardar(LiquidacionDetalle $detalle): LiquidacionDetalle
    {
        $model = LiquidacionDetalleModel::create($this->toModelData($detalle));

        return $this->toEntity($model);
    }

    /**
     * @return LiquidacionDetalle[]
     */
    public function listarPorLiquidacion(int $liquidacionId): array
    {
        return LiquidacionDetalleModel::where('liquidacion_id', $liquidacionId)
            ->get()
            ->map(fn (LiquidacionDetalleModel $model): LiquidacionDetalle => $this->toEntity($model))
            ->all();
    }

    private function toEntity(LiquidacionDetalleModel $model): LiquidacionDetalle
    {
        return new LiquidacionDetalle(
            id: $model->id,
            liquidacionId: $model->liquidacion_id,
            valorizacionEntregaId: $model->valorizacion_entrega_id,
            importe: (float) $model->importe,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(LiquidacionDetalle $detalle): array
    {
        return [
            'liquidacion_id' => $detalle->liquidacionId(),
            'valorizacion_entrega_id' => $detalle->valorizacionEntregaId(),
            'importe' => $detalle->importe(),
        ];
    }
}
