<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use DateTimeImmutable;

class EloquentEncargadoPlantaRepository implements EncargadoPlantaRepository
{
    public function guardar(EncargadoPlanta $encargado): EncargadoPlanta
    {
        $model = EncargadoPlantaModel::create($this->toModelData($encargado));

        return $this->toEntity($model);
    }

    public function actualizar(EncargadoPlanta $encargado): EncargadoPlanta
    {
        $model = EncargadoPlantaModel::findOrFail($encargado->id());
        $model->fill($this->toModelData($encargado));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?EncargadoPlanta
    {
        $model = EncargadoPlantaModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorDni(string $dni): ?EncargadoPlanta
    {
        $model = EncargadoPlantaModel::where('dni', $dni)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUserId(int $userId): ?EncargadoPlanta
    {
        $model = EncargadoPlantaModel::where('user_id', $userId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return EncargadoPlanta[]
     */
    public function listar(): array
    {
        return EncargadoPlantaModel::orderBy('nombres')
            ->get()
            ->map(fn (EncargadoPlantaModel $model): EncargadoPlanta => $this->toEntity($model))
            ->all();
    }

    private function toEntity(EncargadoPlantaModel $model): EncargadoPlanta
    {
        return new EncargadoPlanta(
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
    private function toModelData(EncargadoPlanta $encargado): array
    {
        return [
            'user_id' => $encargado->userId(),
            'dni' => $encargado->dni(),
            'nombres' => $encargado->nombres(),
            'apellidos' => $encargado->apellidos(),
            'telefono' => $encargado->telefono(),
            'estado' => $encargado->estado()->value,
            'fecha_ingreso' => $encargado->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
