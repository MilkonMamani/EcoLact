<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Ventas\ProductoPrecio;
use App\Domain\Ventas\ProductoPrecioRepository;
use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
use DateTimeImmutable;

class EloquentProductoPrecioRepository implements ProductoPrecioRepository
{
    public function guardar(ProductoPrecio $precio): ProductoPrecio
    {
        $model = ProductoPrecioModel::create($this->toModelData($precio));

        return $this->toEntity($model);
    }

    public function buscarVigente(int $productoId, string $tipoCliente, DateTimeImmutable $fecha): ?ProductoPrecio
    {
        foreach ($this->listarPorProducto($productoId) as $precio) {
            if ($precio->tipoCliente() === $tipoCliente && $precio->estaVigente($fecha)) {
                return $precio;
            }
        }

        return null;
    }

    /**
     * @return ProductoPrecio[]
     */
    public function listarPorProducto(int $productoId): array
    {
        return ProductoPrecioModel::where('producto_id', $productoId)
            ->orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (ProductoPrecioModel $model): ProductoPrecio => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProductoPrecioModel $model): ProductoPrecio
    {
        return new ProductoPrecio(
            id: $model->id,
            productoId: $model->producto_id,
            tipoCliente: $model->tipo_cliente,
            precio: (float) $model->precio,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            estado: $model->estado,
            creadoPorUserId: $model->creado_por_user_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ProductoPrecio $precio): array
    {
        return [
            'producto_id' => $precio->productoId(),
            'tipo_cliente' => $precio->tipoCliente(),
            'precio' => $precio->precio(),
            'fecha_inicio' => $precio->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $precio->fechaFin()?->format('Y-m-d'),
            'estado' => $precio->estado(),
            'creado_por_user_id' => $precio->creadoPorUserId(),
        ];
    }
}
