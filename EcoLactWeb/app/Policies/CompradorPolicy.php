<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\CompradorModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider).
 * VENTAS registra a los compradores mayoristas/público que se quieren
 * identificar.
 */
class CompradorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esVentas();
    }

    public function view(User $user, CompradorModel $comprador): bool
    {
        return $user->esVentas();
    }

    public function create(User $user): bool
    {
        return $user->esVentas();
    }
}
