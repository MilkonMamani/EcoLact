<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\RutaModel;
use App\Models\User;

/**
 * La configuración de rutas (crear/editar/asignar proveedores) es exclusiva
 * de ADMINISTRADOR (Gate::before), igual que ZonaPolicy: el ACOPIADOR opera
 * sobre Recorridos (la ejecución concreta), nunca sobre la configuración de
 * la Ruta en sí.
 */
class RutaPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, RutaModel $ruta): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RutaModel $ruta): bool
    {
        return false;
    }
}
