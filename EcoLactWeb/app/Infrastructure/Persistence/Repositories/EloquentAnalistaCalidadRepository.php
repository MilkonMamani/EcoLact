<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use DateTimeImmutable;

class EloquentAnalistaCalidadRepository implements AnalistaCalidadRepository
{
    public function guardar(AnalistaCalidad $analista): AnalistaCalidad
    {
        $model = AnalistaCalidadModel::create($this->toModelData($analista));

        return $this->toEntity($model);
    }

    public function actualizar(AnalistaCalidad $analista): AnalistaCalidad
    {
        $model = AnalistaCalidadModel::findOrFail($analista->id());
        $model->fill($this->toModelData($analista));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?AnalistaCalidad
    {
        $model = AnalistaCalidadModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorDni(string $dni): ?AnalistaCalidad
    {
        $model = AnalistaCalidadModel::where('dni', $dni)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUserId(int $userId): ?AnalistaCalidad
    {
        $model = AnalistaCalidadModel::where('user_id', $userId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return AnalistaCalidad[]
     */
    public function listar(): array
    {
        return AnalistaCalidadModel::orderBy('nombres')
            ->get()
            ->map(fn (AnalistaCalidadModel $model): AnalistaCalidad => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AnalistaCalidadModel $model): AnalistaCalidad
    {
        return new AnalistaCalidad(
            id: $model->id,
            userId: $model->user_id,
            dni: $model->dni,
            nombres: $model->nombres,
            apellidos: $model->apellidos,
            telefono: $model->telefono,
            estado: EstadoUsuario::from($model->estado),
            fechaIngreso: $model->fecha_ingreso ? DateTimeImmutable::createFromInterface($model->fecha_ingreso) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(AnalistaCalidad $analista): array
    {
        return [
            'user_id' => $analista->userId(),
            'dni' => $analista->dni(),
            'nombres' => $analista->nombres(),
            'apellidos' => $analista->apellidos(),
            'telefono' => $analista->telefono(),
            'estado' => $analista->estado()->value,
            'fecha_ingreso' => $analista->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
