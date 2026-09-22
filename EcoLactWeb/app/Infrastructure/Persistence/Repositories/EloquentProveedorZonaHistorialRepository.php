<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Proveedor\ProveedorZonaHistorial;
use App\Domain\Proveedor\ProveedorZonaHistorialRepository;
use App\Infrastructure\Persistence\Models\ProveedorZonaHistorialModel;
use DateTimeImmutable;

class EloquentProveedorZonaHistorialRepository implements ProveedorZonaHistorialRepository
{
    public function guardar(ProveedorZonaHistorial $historial): ProveedorZonaHistorial
    {
        $model = ProveedorZonaHistorialModel::create($this->toModelData($historial));

        return $this->toEntity($model);
    }

    public function buscarVigentePorProveedor(int $proveedorId): ?ProveedorZonaHistorial
    {
        $model = ProveedorZonaHistorialModel::where('proveedor_id', $proveedorId)
            ->whereNull('fecha_fin')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function cerrarVigente(int $proveedorId, DateTimeImmutable $fechaFin): void
    {
        ProveedorZonaHistorialModel::where('proveedor_id', $proveedorId)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => $fechaFin->format('Y-m-d')]);
    }

    /**
     * @return ProveedorZonaHistorial[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return ProveedorZonaHistorialModel::where('proveedor_id', $proveedorId)
            ->get()
            ->map(fn (ProveedorZonaHistorialModel $model): ProveedorZonaHistorial => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProveedorZonaHistorialModel $model): ProveedorZonaHistorial
    {
        return new ProveedorZonaHistorial(
            id: $model->id,
            proveedorId: $model->proveedor_id,
            zonaId: $model->zona_id,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            motivo: $model->motivo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ProveedorZonaHistorial $historial): array
    {
        return [
            'proveedor_id' => $historial->proveedorId(),
            'zona_id' => $historial->zonaId(),
            'fecha_inicio' => $historial->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $historial->fechaFin()?->format('Y-m-d'),
            'motivo' => $historial->motivo(),
        ];
    }
}
