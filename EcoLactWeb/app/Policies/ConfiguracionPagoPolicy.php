<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Models\User;

/**
 * Configuración financiera exclusiva de ADMINISTRADOR (Gate::before). Nadie
 * más ve ni cambia cuánto se paga a proveedores y personal.
 */
class ConfiguracionPagoPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ConfiguracionPagoModel $configuracion): bool
    {
        return false;
    }
}
