<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Calidad\ParametroCalidad;
use App\Domain\Calidad\ParametroCalidadRepository;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;

class EloquentParametroCalidadRepository implements ParametroCalidadRepository
{
    public function guardar(ParametroCalidad $parametro): ParametroCalidad
    {
        $model = ParametroCalidadModel::create($this->toModelData($parametro));

        return $this->toEntity($model);
    }

    public function actualizar(ParametroCalidad $parametro): ParametroCalidad
    {
        $model = ParametroCalidadModel::findOrFail($parametro->id());
        $model->fill($this->toModelData($parametro));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?ParametroCalidad
    {
        $model = ParametroCalidadModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorCodigo(string $codigo): ?ParametroCalidad
    {
        $model = ParametroCalidadModel::where('codigo', $codigo)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return ParametroCalidad[]
     */
    public function listar(): array
    {
        return ParametroCalidadModel::orderBy('nombre')
            ->get()
            ->map(fn (ParametroCalidadModel $model): ParametroCalidad => $this->toEntity($model))
            ->all();
    }

    /**
     * @return ParametroCalidad[]
     */
    public function listarActivos(): array
    {
        return ParametroCalidadModel::where('estado', 'ACTIVO')
            ->get()
            ->map(fn (ParametroCalidadModel $model): ParametroCalidad => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ParametroCalidadModel $model): ParametroCalidad
    {
        return new ParametroCalidad(
            id: $model->id,
            codigo: $model->codigo,
            nombre: $model->nombre,
            unidad: $model->unidad,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ParametroCalidad $parametro): array
    {
        return [
            'codigo' => $parametro->codigo(),
            'nombre' => $parametro->nombre(),
            'unidad' => $parametro->unidad(),
            'estado' => $parametro->estado(),
        ];
    }
}
