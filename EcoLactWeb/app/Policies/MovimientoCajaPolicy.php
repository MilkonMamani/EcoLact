<?php

namespace App\Policies;

use App\Models\User;

/**
 * Caja es exclusiva de ADMINISTRADOR (Gate::before). Los demás roles generan
 * movimientos con sus acciones normales (p. ej. VENTAS al vender), pero no
 * consultan la caja. Es solo lectura: no hay create/update/delete.
 */
class MovimientoCajaPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }
}
