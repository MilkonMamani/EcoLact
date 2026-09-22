<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
use App\Models\User;

/**
 * Configuración financiera exclusiva de ADMINISTRADOR (Gate::before). VENTAS
 * consulta el precio vigente al registrar una venta, pero no lo configura.
 */
class ProductoPrecioPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ProductoPrecioModel $precio): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }
}
