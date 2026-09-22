<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Acopiador\AcopiadorZonaHistorial;
use App\Domain\Acopiador\AcopiadorZonaHistorialRepository;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use DateTimeImmutable;

class EloquentAcopiadorZonaHistorialRepository implements AcopiadorZonaHistorialRepository
{
    public function guardar(AcopiadorZonaHistorial $historial): AcopiadorZonaHistorial
    {
        $model = AcopiadorZonaHistorialModel::create($this->toModelData($historial));

        return $this->toEntity($model);
    }

    public function buscarVigentePorAcopiador(int $acopiadorId): ?AcopiadorZonaHistorial
    {
        $model = AcopiadorZonaHistorialModel::where('acopiador_id', $acopiadorId)
            ->whereNull('fecha_fin')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function cerrarVigente(int $acopiadorId, DateTimeImmutable $fechaFin): void
    {
        AcopiadorZonaHistorialModel::where('acopiador_id', $acopiadorId)
            ->whereNull('fecha_fin')
            ->update(['fecha_fin' => $fechaFin->format('Y-m-d')]);
    }

    /**
     * @return AcopiadorZonaHistorial[]
     */
    public function listarPorAcopiador(int $acopiadorId): array
    {
        return AcopiadorZonaHistorialModel::where('acopiador_id', $acopiadorId)
            ->get()
            ->map(fn (AcopiadorZonaHistorialModel $model): AcopiadorZonaHistorial => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AcopiadorZonaHistorialModel $model): AcopiadorZonaHistorial
    {
        return new AcopiadorZonaHistorial(
            id: $model->id,
            acopiadorId: $model->acopiador_id,
            zonaId: $model->zona_id,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            motivo: $model->motivo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(AcopiadorZonaHistorial $historial): array
    {
        return [
            'acopiador_id' => $historial->acopiadorId(),
            'zona_id' => $historial->zonaId(),
            'fecha_inicio' => $historial->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $historial->fechaFin()?->format('Y-m-d'),
            'motivo' => $historial->motivo(),
        ];
    }
}
