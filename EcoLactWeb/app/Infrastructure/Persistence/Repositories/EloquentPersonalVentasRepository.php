<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use DateTimeImmutable;

class EloquentPersonalVentasRepository implements PersonalVentasRepository
{
    public function guardar(PersonalVentas $personal): PersonalVentas
    {
        $model = PersonalVentasModel::create($this->toModelData($personal));

        return $this->toEntity($model);
    }

    public function actualizar(PersonalVentas $personal): PersonalVentas
    {
        $model = PersonalVentasModel::findOrFail($personal->id());
        $model->fill($this->toModelData($personal));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?PersonalVentas
    {
        $model = PersonalVentasModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorDni(string $dni): ?PersonalVentas
    {
        $model = PersonalVentasModel::where('dni', $dni)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUserId(int $userId): ?PersonalVentas
    {
        $model = PersonalVentasModel::where('user_id', $userId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return PersonalVentas[]
     */
    public function listar(): array
    {
        return PersonalVentasModel::orderBy('nombres')
            ->get()
            ->map(fn (PersonalVentasModel $model): PersonalVentas => $this->toEntity($model))
            ->all();
    }

    private function toEntity(PersonalVentasModel $model): PersonalVentas
    {
        return new PersonalVentas(
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
    private function toModelData(PersonalVentas $personal): array
    {
        return [
            'user_id' => $personal->userId(),
            'dni' => $personal->dni(),
            'nombres' => $personal->nombres(),
            'apellidos' => $personal->apellidos(),
            'telefono' => $personal->telefono(),
            'estado' => $personal->estado()->value,
            'fecha_ingreso' => $personal->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
