<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before. Un ACOPIADOR no
 * administra a otros acopiadores, solo puede ver su propio registro; un
 * PROVEEDOR no tiene ningún acceso a este recurso.
 */
class AcopiadorPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AcopiadorModel $acopiador): bool
    {
        return $user->esAcopiador() && $acopiador->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AcopiadorModel $acopiador): bool
    {
        return false;
    }
}
