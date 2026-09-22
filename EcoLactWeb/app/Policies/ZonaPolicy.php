<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Models\User;

/**
 * La configuración de zonas (crear/editar/eliminar/listar) es exclusiva de
 * ADMINISTRADOR (Gate::before). Ningún Use Case actual exige que un
 * ACOPIADOR o PROVEEDOR consulten el catálogo de zonas directamente (su
 * propia zona ya la conocen a través de su registro de Acopiador/
 * Proveedor), así que no se les concede lectura para no inventar un acceso
 * sin necesidad funcional demostrada.
 */
class ZonaPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ZonaModel $zona): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ZonaModel $zona): bool
    {
        return false;
    }

    public function delete(User $user, ZonaModel $zona): bool
    {
        return false;
    }
}
