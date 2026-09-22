<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Models\User;

/**
 * El inventario de productos terminados (consulta) es exclusivo de
 * ADMINISTRADOR (Gate::before). Los productos no se crean ni se editan: nacen
 * desde su receta. VENTAS y ENCARGADO_PLANTA consultan
 * productos activos a través de Use Cases dedicados (ObtenerProductosActivos),
 * no de esta Policy.
 */
class ProductoPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ProductoModel $producto): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProductoModel $producto): bool
    {
        return false;
    }
}
