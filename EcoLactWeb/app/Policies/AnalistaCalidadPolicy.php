<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before. Un analista de
 * CALIDAD no administra a otros analistas, solo puede ver su propio
 * registro.
 */
class AnalistaCalidadPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AnalistaCalidadModel $analista): bool
    {
        return $user->esCalidad() && $analista->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AnalistaCalidadModel $analista): bool
    {
        return false;
    }
}
