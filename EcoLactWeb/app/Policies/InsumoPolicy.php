<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider).
 * ENCARGADO_PLANTA gestiona el catálogo e inventario de insumos que
 * consume en producción.
 */
class InsumoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function view(User $user, InsumoModel $insumo): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function create(User $user): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function update(User $user, InsumoModel $insumo): bool
    {
        return $user->esEncargadoPlanta();
    }
}
