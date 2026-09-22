<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Recorrido\RecorridoDetalle;
use App\Domain\Recorrido\RecorridoDetalleRepository;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;

class EloquentRecorridoDetalleRepository implements RecorridoDetalleRepository
{
    public function guardar(RecorridoDetalle $detalle): RecorridoDetalle
    {
        $model = RecorridoDetalleModel::create($this->toModelData($detalle));

        return $this->toEntity($model);
    }

    public function actualizar(RecorridoDetalle $detalle): RecorridoDetalle
    {
        $model = RecorridoDetalleModel::findOrFail($detalle->id());
        $model->fill($this->toModelData($detalle));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?RecorridoDetalle
    {
        $model = RecorridoDetalleModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorRecorridoYProveedor(int $recorridoId, int $proveedorId): ?RecorridoDetalle
    {
        $model = RecorridoDetalleModel::where('recorrido_id', $recorridoId)
            ->where('proveedor_id', $proveedorId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return RecorridoDetalle[]
     */
    public function listarPorRecorrido(int $recorridoId): array
    {
        return RecorridoDetalleModel::where('recorrido_id', $recorridoId)
            ->orderBy('orden_visita')
            ->get()
            ->map(fn (RecorridoDetalleModel $model): RecorridoDetalle => $this->toEntity($model))
            ->all();
    }

    private function toEntity(RecorridoDetalleModel $model): RecorridoDetalle
    {
        return new RecorridoDetalle(
            id: $model->id,
            recorridoId: $model->recorrido_id,
            proveedorId: $model->proveedor_id,
            rutaDetalleId: $model->ruta_detalle_id,
            ordenVisita: $model->orden_visita,
            latitud: $model->latitud !== null ? (float) $model->latitud : null,
            longitud: $model->longitud !== null ? (float) $model->longitud : null,
            referenciaUbicacion: $model->referencia_ubicacion,
            estado: EstadoParadaRecorrido::from($model->estado),
            observacion: $model->observacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(RecorridoDetalle $detalle): array
    {
        return [
            'recorrido_id' => $detalle->recorridoId(),
            'proveedor_id' => $detalle->proveedorId(),
            'ruta_detalle_id' => $detalle->rutaDetalleId(),
            'orden_visita' => $detalle->ordenVisita(),
            'latitud' => $detalle->latitud(),
            'longitud' => $detalle->longitud(),
            'referencia_ubicacion' => $detalle->referenciaUbicacion(),
            'estado' => $detalle->estado()->value,
            'observacion' => $detalle->observacion(),
        ];
    }
}
