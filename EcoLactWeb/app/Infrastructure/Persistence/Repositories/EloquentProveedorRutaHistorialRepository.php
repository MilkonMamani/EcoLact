<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Ruta\ProveedorRutaHistorial;
use App\Domain\Ruta\ProveedorRutaHistorialRepository;
use App\Infrastructure\Persistence\Models\ProveedorRutaHistorialModel;
use DateTimeImmutable;

class EloquentProveedorRutaHistorialRepository implements ProveedorRutaHistorialRepository
{
    public function guardar(ProveedorRutaHistorial $historial): ProveedorRutaHistorial
    {
        $model = ProveedorRutaHistorialModel::create($this->toModelData($historial));

        return $this->toEntity($model);
    }

    public function buscarVigentePorProveedor(int $proveedorId): ?ProveedorRutaHistorial
    {
        $model = ProveedorRutaHistorialModel::where('proveedor_id', $proveedorId)
            ->whereNull('fecha_fin')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function cerrarVigente(int $proveedorId, DateTimeImmutable $fechaFin): void
    {
        ProveedorRutaHistorialModel::where('proveedor_id', $proveedorId)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => $fechaFin->format('Y-m-d')]);
    }

    /**
     * @return ProveedorRutaHistorial[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return ProveedorRutaHistorialModel::where('proveedor_id', $proveedorId)
            ->orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (ProveedorRutaHistorialModel $model): ProveedorRutaHistorial => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProveedorRutaHistorialModel $model): ProveedorRutaHistorial
    {
        return new ProveedorRutaHistorial(
            id: $model->id,
            proveedorId: $model->proveedor_id,
            rutaId: $model->ruta_id,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            motivo: $model->motivo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ProveedorRutaHistorial $historial): array
    {
        return [
            'proveedor_id' => $historial->proveedorId(),
            'ruta_id' => $historial->rutaId(),
            'fecha_inicio' => $historial->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $historial->fechaFin()?->format('Y-m-d'),
            'motivo' => $historial->motivo(),
        ];
    }
}
