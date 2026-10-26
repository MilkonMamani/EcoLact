<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Pago\PersonalPagable;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Models\User;
use DateTimeImmutable;

/**
 * Cada rol del personal puede tener su ficha en su propia tabla (acopiadores,
 * analistas_calidad, encargados_planta, personal_ventas) vinculada a users; los
 * demás (administrador, roles nuevos) solo existen como usuario. Esta consulta
 * unifica todos como "personal pagable" leyendo users por role_id, sin
 * duplicar personas en otra tabla.
 *
 * Para los roles que sí tienen ficha dedicada, tener el rol en `users` no
 * basta: solo se considera personal pagable a quien además tiene una ficha
 * real y ACTIVA en su tabla (evita generar sueldos de usuarios huérfanos,
 * p.ej. creados a mano con role_id de ACOPIADOR pero sin registro en
 * `acopiadores`).
 */
final class EloquentPersonalPagableQuery implements PersonalPagableQuery
{
    /** Relación de ficha (User::hasOne) por nombre de rol, para los roles que tienen tabla propia. */
    private const FICHA_POR_ROL = [
        'ACOPIADOR' => 'acopiador',
        'CALIDAD' => 'analistaCalidad',
        'ENCARGADO_PLANTA' => 'encargadoPlanta',
        'VENTAS' => 'personalVentas',
    ];

    public function activosPorRol(int $rolId): array
    {
        $relacionFicha = $this->relacionFichaDelRol($rolId);

        return User::query()
            ->where('role_id', $rolId)
            ->where('estado', EstadoUsuario::ACTIVO->value)
            ->when($relacionFicha !== null, fn ($query) => $query->with($relacionFicha))
            ->orderBy('username')
            ->get()
            ->map(fn (User $usuario): ?PersonalPagable => $this->aPersonalPagable($usuario, $relacionFicha))
            ->filter()
            ->values()
            ->all();
    }

    public function trabajadorActivo(int $userId): ?PersonalPagable
    {
        $usuario = User::query()->where('estado', EstadoUsuario::ACTIVO->value)->find($userId);

        if ($usuario === null || ! RoleModel::whereKey($usuario->role_id)->where('es_remunerable', true)->exists()) {
            return null;
        }

        return $this->aPersonalPagable($usuario, $this->relacionFichaDelRol($usuario->role_id));
    }

    private function aPersonalPagable(User $usuario, ?string $relacionFicha): ?PersonalPagable
    {
        // Rol sin ficha dedicada (p.ej. administrador o un rol nuevo): el usuario mismo es el personal pagable.
        if ($relacionFicha === null) {
            return new PersonalPagable(
                userId: $usuario->id,
                rolId: $usuario->role_id,
                nombre: $usuario->username,
                fechaAlta: DateTimeImmutable::createFromInterface($usuario->created_at),
            );
        }

        $ficha = $usuario->{$relacionFicha};

        // Sin ficha real, o ficha inactiva: no es personal pagable, aunque el usuario tenga el role_id.
        if ($ficha === null || $ficha->estado !== EstadoUsuario::ACTIVO->value) {
            return null;
        }

        return new PersonalPagable(
            userId: $usuario->id,
            rolId: $usuario->role_id,
            nombre: trim($ficha->nombres.' '.$ficha->apellidos),
            fechaAlta: DateTimeImmutable::createFromInterface($ficha->fecha_ingreso ?? $ficha->created_at),
            dni: $ficha->dni,
        );
    }

    public function usuariosSinFichaReal(int $rolId): array
    {
        $relacionFicha = $this->relacionFichaDelRol($rolId);

        if ($relacionFicha === null) {
            return [];
        }

        return User::query()
            ->where('role_id', $rolId)
            ->whereDoesntHave($relacionFicha)
            ->pluck('id')
            ->all();
    }

    private function relacionFichaDelRol(int $rolId): ?string
    {
        return self::FICHA_POR_ROL[RoleModel::find($rolId)?->nombre] ?? null;
    }
}
