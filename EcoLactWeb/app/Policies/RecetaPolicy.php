<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\RecetaModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider) y
 * es quien configura las recetas (crear, editar, versionar, activar). El
 * ENCARGADO_PLANTA solo las consulta: necesita saber qué lleva cada producto
 * pero no puede cambiarlas.
 */
class RecetaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function view(User $user, RecetaModel $receta): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RecetaModel $receta): bool
    {
        return false;
    }
}
