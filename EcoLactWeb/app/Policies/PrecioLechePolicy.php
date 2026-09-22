<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Models\User;

/**
 * Configuración financiera exclusiva de ADMINISTRADOR (Gate::before). No se
 * expone al ACOPIADOR ni al PROVEEDOR: el proveedor solo debe ver el
 * resultado final ya aplicado en su liquidación/pago (LiquidacionPolicy),
 * no el precio base configurado.
 */
class PrecioLechePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, PrecioLecheModel $precio): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PrecioLecheModel $precio): bool
    {
        return false;
    }
}
