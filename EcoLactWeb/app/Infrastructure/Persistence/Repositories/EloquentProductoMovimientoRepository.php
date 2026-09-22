<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use DateTimeImmutable;

class EloquentProductoMovimientoRepository implements ProductoMovimientoRepository
{
    public function guardar(ProductoMovimiento $movimiento): ProductoMovimiento
    {
        $model = ProductoMovimientoModel::create($this->toModelData($movimiento));

        return $this->toEntity($model);
    }

    /**
     * @return ProductoMovimiento[]
     */
    public function listarPorProducto(int $productoId): array
    {
        return ProductoMovimientoModel::where('producto_id', $productoId)
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (ProductoMovimientoModel $model): ProductoMovimiento => $this->toEntity($model))
            ->all();
    }

    /**
     * @return ProductoMovimiento[]
     */
    public function listar(): array
    {
        return ProductoMovimientoModel::orderByDesc('fecha')
            ->get()
            ->map(fn (ProductoMovimientoModel $model): ProductoMovimiento => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProductoMovimientoModel $model): ProductoMovimiento
    {
        return new ProductoMovimiento(
            id: $model->id,
            productoId: $model->producto_id,
            tipo: $model->tipo,
            cantidad: (float) $model->cantidad,
            loteProduccionId: $model->lote_produccion_id,
            ventaProductoId: $model->venta_producto_id,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            registradoPorUserId: $model->registrado_por_user_id,
            observacion: $model->observacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ProductoMovimiento $movimiento): array
    {
        return [
            'producto_id' => $movimiento->productoId(),
            'tipo' => $movimiento->tipo(),
            'cantidad' => $movimiento->cantidad(),
            'lote_produccion_id' => $movimiento->loteProduccionId(),
            'venta_producto_id' => $movimiento->ventaProductoId(),
            'fecha' => $movimiento->fecha()->format('Y-m-d H:i:s'),
            'observacion' => $movimiento->observacion(),
            'registrado_por_user_id' => $movimiento->registradoPorUserId(),
        ];
    }
}
