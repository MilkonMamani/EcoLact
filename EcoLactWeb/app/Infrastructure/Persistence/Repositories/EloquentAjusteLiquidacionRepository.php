<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\AjusteLiquidacion;
use App\Domain\Pago\AjusteLiquidacionRepository;
use App\Infrastructure\Persistence\Models\AjusteLiquidacionModel;

class EloquentAjusteLiquidacionRepository implements AjusteLiquidacionRepository
{
    public function guardar(AjusteLiquidacion $ajuste): AjusteLiquidacion
    {
        $model = AjusteLiquidacionModel::create($this->toModelData($ajuste));

        return $this->toEntity($model);
    }

    /**
     * @return AjusteLiquidacion[]
     */
    public function listarPorLiquidacion(int $liquidacionId): array
    {
        return AjusteLiquidacionModel::where('liquidacion_id', $liquidacionId)
            ->get()
            ->map(fn (AjusteLiquidacionModel $model): AjusteLiquidacion => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AjusteLiquidacionModel $model): AjusteLiquidacion
    {
        return new AjusteLiquidacion(
            id: $model->id,
            liquidacionId: $model->liquidacion_id,
            sancionId: $model->sancion_id,
            tipo: $model->tipo,
            descripcion: $model->descripcion,
            monto: (float) $model->monto,
            creadoPorUserId: $model->creado_por_user_id,
            ventaProductoId: $model->venta_producto_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(AjusteLiquidacion $ajuste): array
    {
        return [
            'liquidacion_id' => $ajuste->liquidacionId(),
            'sancion_id' => $ajuste->sancionId(),
            'tipo' => $ajuste->tipo(),
            'descripcion' => $ajuste->descripcion(),
            'monto' => $ajuste->monto(),
            'creado_por_user_id' => $ajuste->creadoPorUserId(),
            'venta_producto_id' => $ajuste->ventaProductoId(),
        ];
    }
}
