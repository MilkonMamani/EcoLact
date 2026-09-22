<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use App\Models\User;

/**
 * Configuración financiera exclusiva de ADMINISTRADOR (Gate::before). Igual
 * que PrecioLechePolicy: no se expone al ACOPIADOR ni al PROVEEDOR.
 */
class ReglaPagoCalidadPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ReglaPagoCalidadModel $regla): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReglaPagoCalidadModel $regla): bool
    {
        return false;
    }
}
