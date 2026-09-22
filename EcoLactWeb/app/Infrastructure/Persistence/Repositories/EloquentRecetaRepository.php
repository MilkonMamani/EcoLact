<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\Receta;
use App\Domain\Produccion\RecetaDetalle;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Shared\Enums\EstadoReceta;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;

class EloquentRecetaRepository implements RecetaRepository
{
    public function guardar(Receta $receta): Receta
    {
        $model = RecetaModel::create($this->toModelData($receta));
        $this->reemplazarDetalles($model, $receta);

        return $this->toEntity($model->fresh('detalles'));
    }

    public function actualizar(Receta $receta): Receta
    {
        $model = RecetaModel::findOrFail($receta->id());
        $model->fill($this->toModelData($receta));
        $model->save();
        $this->reemplazarDetalles($model, $receta);

        return $this->toEntity($model->fresh('detalles'));
    }

    public function cambiarEstado(int $recetaId, EstadoReceta $estado): void
    {
        $model = RecetaModel::findOrFail($recetaId);

        $model->estado = $estado->value;
        $model->producto_activo_id = $estado === EstadoReceta::ACTIVA ? $model->producto_id : null;
        $model->save();
    }

    public function buscarPorId(int $id): ?Receta
    {
        $model = RecetaModel::with('detalles')->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarActivaPorProducto(int $productoId): ?Receta
    {
        $model = RecetaModel::with('detalles')
            ->where('producto_id', $productoId)
            ->where('estado', EstadoReceta::ACTIVA->value)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function versionMaximaDelProducto(int $productoId): int
    {
        return (int) RecetaModel::where('producto_id', $productoId)->max('version');
    }

    public function estaEnUso(int $recetaId): bool
    {
        return ProduccionDetalleModel::where('receta_id', $recetaId)->exists();
    }

    private function reemplazarDetalles(RecetaModel $model, Receta $receta): void
    {
        $model->detalles()->delete();

        foreach ($receta->detalles() as $detalle) {
            RecetaDetalleModel::create([
                'receta_id' => $model->id,
                'insumo_id' => $detalle->insumoId(),
                'cantidad' => $detalle->cantidad(),
                'unidad' => $detalle->unidad(),
                'observacion' => $detalle->observacion(),
            ]);
        }
    }

    private function toEntity(RecetaModel $model): Receta
    {
        return new Receta(
            id: $model->id,
            productoId: $model->producto_id,
            nombre: $model->nombre,
            descripcion: $model->descripcion,
            rendimientoCantidad: (float) $model->rendimiento_cantidad,
            rendimientoUnidad: $model->rendimiento_unidad,
            version: (int) $model->version,
            estado: EstadoReceta::from($model->estado),
            createdByUserId: $model->created_by_user_id,
            detalles: $model->detalles
                ->sortBy('id')
                ->map(fn (RecetaDetalleModel $detalle): RecetaDetalle => new RecetaDetalle(
                    id: $detalle->id,
                    insumoId: $detalle->insumo_id,
                    cantidad: (float) $detalle->cantidad,
                    unidad: $detalle->unidad,
                    observacion: $detalle->observacion,
                ))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Receta $receta): array
    {
        return [
            'producto_id' => $receta->productoId(),
            'nombre' => $receta->nombre(),
            'descripcion' => $receta->descripcion(),
            'rendimiento_cantidad' => $receta->rendimientoCantidad(),
            'rendimiento_unidad' => $receta->rendimientoUnidad(),
            'version' => $receta->version(),
            'estado' => $receta->estado()->value,
            'producto_activo_id' => $receta->estaActiva() ? $receta->productoId() : null,
            'created_by_user_id' => $receta->createdByUserId(),
        ];
    }
}
