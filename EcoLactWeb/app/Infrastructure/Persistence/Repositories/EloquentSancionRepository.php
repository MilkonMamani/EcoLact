<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Calidad\Sancion;
use App\Domain\Calidad\SancionRepository;
use App\Infrastructure\Persistence\Models\SancionModel;
use DateTimeImmutable;

class EloquentSancionRepository implements SancionRepository
{
    public function guardar(Sancion $sancion): Sancion
    {
        $model = SancionModel::create($this->toModelData($sancion));

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Sancion
    {
        $model = SancionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Sancion[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return SancionModel::where('proveedor_id', $proveedorId)
            ->orderBy('fecha')
            ->get()
            ->map(fn (SancionModel $model): Sancion => $this->toEntity($model))
            ->all();
    }

    private function toEntity(SancionModel $model): Sancion
    {
        return new Sancion(
            id: $model->id,
            proveedorId: $model->proveedor_id,
            analisisId: $model->analisis_id,
            tipo: $model->tipo,
            nivelAdvertencia: $model->nivel_advertencia,
            descripcion: $model->descripcion,
            tipoPenalizacion: $model->tipo_penalizacion,
            valorPenalizacion: $model->valor_penalizacion !== null ? (float) $model->valor_penalizacion : null,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            estado: $model->estado,
            creadoPorUserId: $model->creado_por_user_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Sancion $sancion): array
    {
        return [
            'proveedor_id' => $sancion->proveedorId(),
            'analisis_id' => $sancion->analisisId(),
            'tipo' => $sancion->tipo(),
            'nivel_advertencia' => $sancion->nivelAdvertencia(),
            'descripcion' => $sancion->descripcion(),
            'tipo_penalizacion' => $sancion->tipoPenalizacion(),
            'valor_penalizacion' => $sancion->valorPenalizacion(),
            'fecha' => $sancion->fecha()->format('Y-m-d'),
            'estado' => $sancion->estado(),
            'creado_por_user_id' => $sancion->creadoPorUserId(),
        ];
    }
}
