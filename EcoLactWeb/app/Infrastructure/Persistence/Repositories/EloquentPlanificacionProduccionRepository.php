<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\PlanificacionProduccion;
use App\Domain\Produccion\PlanificacionProduccionRepository;
use App\Infrastructure\Persistence\Models\PlanificacionProduccionModel;

class EloquentPlanificacionProduccionRepository implements PlanificacionProduccionRepository
{
    public function guardar(PlanificacionProduccion $planificacion): PlanificacionProduccion
    {
        return $this->toEntity(PlanificacionProduccionModel::create($this->toModelData($planificacion)));
    }

    public function actualizar(PlanificacionProduccion $planificacion): PlanificacionProduccion
    {
        $model = PlanificacionProduccionModel::findOrFail($planificacion->id());
        $model->fill($this->toModelData($planificacion));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?PlanificacionProduccion
    {
        $model = PlanificacionProduccionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    private function toEntity(PlanificacionProduccionModel $model): PlanificacionProduccion
    {
        return new PlanificacionProduccion(
            id: $model->id,
            codigo: $model->codigo,
            registradoPorUserId: $model->registrado_por_user_id,
            observacion: $model->observacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(PlanificacionProduccion $planificacion): array
    {
        return [
            'codigo' => $planificacion->codigo(),
            'observacion' => $planificacion->observacion(),
            'registrado_por_user_id' => $planificacion->registradoPorUserId(),
        ];
    }
}
