<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\PeriodoLiquidacion;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use DateTimeImmutable;

class EloquentPeriodoLiquidacionRepository implements PeriodoLiquidacionRepository
{
    public function guardar(PeriodoLiquidacion $periodo): PeriodoLiquidacion
    {
        $model = PeriodoLiquidacionModel::create($this->toModelData($periodo));

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?PeriodoLiquidacion
    {
        $model = PeriodoLiquidacionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorFechas(DateTimeImmutable $fechaInicio, DateTimeImmutable $fechaFin): ?PeriodoLiquidacion
    {
        // whereDate() en lugar de comparar strings: la columna se persiste con
        // componente de hora (Y-m-d H:i:s) aunque el cast del modelo sea 'date'.
        $model = PeriodoLiquidacionModel::whereDate('fecha_inicio', $fechaInicio->format('Y-m-d'))
            ->whereDate('fecha_fin', $fechaFin->format('Y-m-d'))
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    private function toEntity(PeriodoLiquidacionModel $model): PeriodoLiquidacion
    {
        return new PeriodoLiquidacion(
            id: $model->id,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: DateTimeImmutable::createFromInterface($model->fecha_fin),
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(PeriodoLiquidacion $periodo): array
    {
        return [
            'fecha_inicio' => $periodo->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $periodo->fechaFin()->format('Y-m-d'),
            'estado' => $periodo->estado(),
        ];
    }
}
