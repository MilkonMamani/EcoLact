<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use DateTimeImmutable;

class EloquentAcopiadorRepository implements AcopiadorRepository
{
    public function guardar(Acopiador $acopiador): Acopiador
    {
        $model = AcopiadorModel::create($this->toModelData($acopiador));

        return $this->toEntity($model);
    }

    public function actualizar(Acopiador $acopiador): Acopiador
    {
        $model = AcopiadorModel::findOrFail($acopiador->id());
        $model->fill($this->toModelData($acopiador));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Acopiador
    {
        $model = AcopiadorModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorDni(string $dni): ?Acopiador
    {
        $model = AcopiadorModel::where('dni', $dni)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUserId(int $userId): ?Acopiador
    {
        $model = AcopiadorModel::where('user_id', $userId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Acopiador[]
     */
    public function listarPorZona(int $zonaId): array
    {
        return AcopiadorModel::where('zona_id', $zonaId)
            ->get()
            ->map(fn (AcopiadorModel $model): Acopiador => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Acopiador[]
     */
    public function listar(): array
    {
        return AcopiadorModel::orderBy('nombres')
            ->get()
            ->map(fn (AcopiadorModel $model): Acopiador => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AcopiadorModel $model): Acopiador
    {
        return new Acopiador(
            id: $model->id,
            userId: $model->user_id,
            zonaId: $model->zona_id,
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
    private function toModelData(Acopiador $acopiador): array
    {
        return [
            'user_id' => $acopiador->userId(),
            'zona_id' => $acopiador->zonaId(),
            'dni' => $acopiador->dni(),
            'nombres' => $acopiador->nombres(),
            'apellidos' => $acopiador->apellidos(),
            'telefono' => $acopiador->telefono(),
            'estado' => $acopiador->estado()->value,
            'fecha_ingreso' => $acopiador->fechaIngreso()?->format('Y-m-d'),
        ];
    }
}
