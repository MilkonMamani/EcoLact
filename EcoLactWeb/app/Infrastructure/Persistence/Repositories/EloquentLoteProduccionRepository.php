<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use DateTimeImmutable;

class EloquentLoteProduccionRepository implements LoteProduccionRepository
{
    public function guardar(LoteProduccion $lote): LoteProduccion
    {
        $model = LoteProduccionModel::create($this->toModelData($lote));

        return $this->toEntity($model);
    }

    public function actualizar(LoteProduccion $lote): LoteProduccion
    {
        $model = LoteProduccionModel::findOrFail($lote->id());
        $model->fill($this->toModelData($lote));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?LoteProduccion
    {
        $model = LoteProduccionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function bloquearPorId(int $id): ?LoteProduccion
    {
        $model = LoteProduccionModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorCodigo(string $codigo): ?LoteProduccion
    {
        $model = LoteProduccionModel::where('codigo', $codigo)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return LoteProduccion[]
     */
    public function listarPorEstado(string $estado): array
    {
        return LoteProduccionModel::where('estado', $estado)
            ->get()
            ->map(fn (LoteProduccionModel $model): LoteProduccion => $this->toEntity($model))
            ->all();
    }

    /**
     * @return LoteProduccion[]
     */
    public function listar(): array
    {
        return LoteProduccionModel::orderByDesc('fecha_hora_inicio')
            ->get()
            ->map(fn (LoteProduccionModel $model): LoteProduccion => $this->toEntity($model))
            ->all();
    }

    private function toEntity(LoteProduccionModel $model): LoteProduccion
    {
        return new LoteProduccion(
            id: $model->id,
            codigo: $model->codigo,
            fechaHoraInicio: DateTimeImmutable::createFromInterface($model->fecha_hora_inicio),
            fechaHoraFin: $model->fecha_hora_fin ? DateTimeImmutable::createFromInterface($model->fecha_hora_fin) : null,
            estado: $model->estado,
            registradoPorUserId: $model->registrado_por_user_id,
            observacion: $model->observacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(LoteProduccion $lote): array
    {
        return [
            'codigo' => $lote->codigo(),
            'fecha_hora_inicio' => $lote->fechaHoraInicio()->format('Y-m-d H:i:s'),
            'fecha_hora_fin' => $lote->fechaHoraFin()?->format('Y-m-d H:i:s'),
            'estado' => $lote->estado(),
            'registrado_por_user_id' => $lote->registradoPorUserId(),
            'observacion' => $lote->observacion(),
        ];
    }
}
