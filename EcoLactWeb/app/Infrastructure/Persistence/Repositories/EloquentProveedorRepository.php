<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use DateTimeImmutable;

class EloquentProveedorRepository implements ProveedorRepository
{
    public function guardar(Proveedor $proveedor): Proveedor
    {
        $model = ProveedorModel::create($this->toModelData($proveedor));

        return $this->toEntity($model);
    }

    public function actualizar(Proveedor $proveedor): Proveedor
    {
        $model = ProveedorModel::findOrFail($proveedor->id());
        $model->fill($this->toModelData($proveedor));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Proveedor
    {
        $model = ProveedorModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorDni(string $dni): ?Proveedor
    {
        $model = ProveedorModel::where('dni', $dni)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUserId(int $userId): ?Proveedor
    {
        $model = ProveedorModel::where('user_id', $userId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorQrToken(string $qrToken): ?Proveedor
    {
        $model = ProveedorModel::where('qr_token', $qrToken)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Proveedor[]
     */
    public function listarPorZona(int $zonaId): array
    {
        return ProveedorModel::where('zona_id', $zonaId)
            ->get()
            ->map(fn (ProveedorModel $model): Proveedor => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Proveedor[]
     */
    public function listar(): array
    {
        return ProveedorModel::orderBy('nombres')
            ->get()
            ->map(fn (ProveedorModel $model): Proveedor => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ProveedorModel $model): Proveedor
    {
        return new Proveedor(
            id: $model->id,
            userId: $model->user_id,
            zonaId: $model->zona_id,
            dni: $model->dni,
            nombres: $model->nombres,
            apellidos: $model->apellidos,
            telefono: $model->telefono,
            qrToken: $model->qr_token,
            estado: EstadoUsuario::from($model->estado),
            fechaRegistro: DateTimeImmutable::createFromInterface($model->fecha_registro),
            advertenciasCount: $model->advertencias_count,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Proveedor $proveedor): array
    {
        return [
            'user_id' => $proveedor->userId(),
            'zona_id' => $proveedor->zonaId(),
            'dni' => $proveedor->dni(),
            'nombres' => $proveedor->nombres(),
            'apellidos' => $proveedor->apellidos(),
            'telefono' => $proveedor->telefono(),
            'qr_token' => $proveedor->qrToken(),
            'estado' => $proveedor->estado()->value,
            'fecha_registro' => $proveedor->fechaRegistro()->format('Y-m-d'),
            'advertencias_count' => $proveedor->advertenciasCount(),
        ];
    }
}
