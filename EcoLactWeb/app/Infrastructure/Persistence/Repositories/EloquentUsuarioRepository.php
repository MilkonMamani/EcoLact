<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Models\User;
use DateTimeImmutable;

class EloquentUsuarioRepository implements UsuarioRepository
{
    public function guardar(Usuario $usuario): Usuario
    {
        $model = User::create($this->toModelData($usuario));

        return $this->toEntity($model);
    }

    public function actualizar(Usuario $usuario): Usuario
    {
        $model = User::findOrFail($usuario->id());
        $model->fill($this->toModelData($usuario));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Usuario
    {
        $model = User::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUsername(string $username): ?Usuario
    {
        $model = User::where('username', $username)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Usuario[]
     */
    public function listarActivosPorRole(string $nombreRol): array
    {
        return User::whereHas('role', fn ($query) => $query->where('nombre', $nombreRol))
            ->where('estado', EstadoUsuario::ACTIVO->value)
            ->get()
            ->map(fn (User $model): Usuario => $this->toEntity($model))
            ->all();
    }

    private function toEntity(User $model): Usuario
    {
        return new Usuario(
            id: $model->id,
            roleId: $model->role_id,
            username: $model->username,
            password: $model->password,
            estado: EstadoUsuario::from($model->estado),
            debeCambiarPassword: (bool) $model->debe_cambiar_password,
            ultimoAcceso: $model->ultimo_acceso ? DateTimeImmutable::createFromInterface($model->ultimo_acceso) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Usuario $usuario): array
    {
        return [
            'role_id' => $usuario->roleId(),
            'username' => $usuario->username(),
            'password' => $usuario->password(),
            'estado' => $usuario->estado()->value,
            'debe_cambiar_password' => $usuario->debeCambiarPassword(),
            'ultimo_acceso' => $usuario->ultimoAcceso()?->format('Y-m-d H:i:s'),
        ];
    }
}
