<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before. Un miembro del
 * personal de VENTAS no administra a otros, solo puede ver su propio
 * registro.
 */
class PersonalVentasPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, PersonalVentasModel $personal): bool
    {
        return $user->esVentas() && $personal->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PersonalVentasModel $personal): bool
    {
        return false;
    }
}
