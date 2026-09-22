<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before. Un ENCARGADO_PLANTA
 * no administra a otros encargados, solo puede ver su propio registro.
 */
class EncargadoPlantaPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, EncargadoPlantaModel $encargado): bool
    {
        return $user->esEncargadoPlanta() && $encargado->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EncargadoPlantaModel $encargado): bool
    {
        return false;
    }
}
