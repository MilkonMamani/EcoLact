<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Calidad\ReglaCalidad;
use App\Domain\Calidad\ReglaCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\ReglaCalidadModel;
use DateTimeImmutable;

class EloquentReglaCalidadRepository implements ReglaCalidadRepository
{
    public function guardar(ReglaCalidad $regla): ReglaCalidad
    {
        $model = ReglaCalidadModel::create($this->toModelData($regla));

        return $this->toEntity($model);
    }

    public function actualizar(ReglaCalidad $regla): ReglaCalidad
    {
        $model = ReglaCalidadModel::findOrFail($regla->id());
        $model->fill($this->toModelData($regla));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?ReglaCalidad
    {
        $model = ReglaCalidadModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarVigentePorParametro(int $parametroId, DateTimeImmutable $fecha): ?ReglaCalidad
    {
        $model = ReglaCalidadModel::where('parametro_id', $parametroId)
            ->vigentesEn($fecha)
            ->orderBy('prioridad')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return ReglaCalidad[]
     */
    public function listar(): array
    {
        return ReglaCalidadModel::orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (ReglaCalidadModel $model): ReglaCalidad => $this->toEntity($model))
            ->all();
    }

    /**
     * @return ReglaCalidad[]
     */
    public function listarPorParametro(int $parametroId): array
    {
        return ReglaCalidadModel::where('parametro_id', $parametroId)
            ->get()
            ->map(fn (ReglaCalidadModel $model): ReglaCalidad => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ReglaCalidadModel $model): ReglaCalidad
    {
        return new ReglaCalidad(
            id: $model->id,
            parametroId: $model->parametro_id,
            valorMinimo: $model->valor_minimo !== null ? (float) $model->valor_minimo : null,
            valorMaximo: $model->valor_maximo !== null ? (float) $model->valor_maximo : null,
            resultadoIncumplimiento: ResultadoCalidad::from($model->resultado_incumplimiento),
            prioridad: $model->prioridad,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ReglaCalidad $regla): array
    {
        return [
            'parametro_id' => $regla->parametroId(),
            'valor_minimo' => $regla->valorMinimo(),
            'valor_maximo' => $regla->valorMaximo(),
            'resultado_incumplimiento' => $regla->resultadoIncumplimiento()->value,
            'prioridad' => $regla->prioridad(),
            'fecha_inicio' => $regla->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $regla->fechaFin()?->format('Y-m-d'),
            'estado' => $regla->estado(),
        ];
    }
}
