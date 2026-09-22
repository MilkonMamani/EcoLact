<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global (Gate::before). El PROVEEDOR solo
 * puede VER sus propias liquidaciones. El ACOPIADOR no administra ni
 * consulta liquidaciones: no hay un requerimiento real documentado para
 * ello, así que no se inventa el acceso.
 */
class LiquidacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esProveedor();
    }

    public function view(User $user, LiquidacionModel $liquidacion): bool
    {
        return $user->esProveedor()
            && $user->proveedor !== null
            && $user->proveedor->id === $liquidacion->proveedor_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LiquidacionModel $liquidacion): bool
    {
        return false;
    }
}
