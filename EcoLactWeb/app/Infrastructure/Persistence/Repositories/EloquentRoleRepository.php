<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Role;
use App\Infrastructure\Persistence\Models\RoleModel;

class EloquentRoleRepository implements RoleRepository
{
    public function buscarPorId(int $id): ?Role
    {
        $model = RoleModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorNombre(string $nombre): ?Role
    {
        $model = RoleModel::where('nombre', $nombre)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listarRemunerables(): array
    {
        return RoleModel::where('es_remunerable', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (RoleModel $model): Role => $this->toEntity($model))
            ->all();
    }

    private function toEntity(RoleModel $model): Role
    {
        return new Role(
            id: $model->id,
            nombre: $model->nombre,
            descripcion: $model->descripcion,
            esRemunerable: (bool) $model->es_remunerable,
        );
    }
}
