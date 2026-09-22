<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global (Gate::before). El PROVEEDOR solo
 * puede VER sus propios pagos (a través de la liquidación a la que
 * pertenece el pago). El ACOPIADOR no administra pagos.
 */
class PagoProveedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esProveedor();
    }

    public function view(User $user, PagoProveedorModel $pago): bool
    {
        return $user->esProveedor()
            && $user->proveedor !== null
            && $user->proveedor->id === $pago->liquidacion?->proveedor_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PagoProveedorModel $pago): bool
    {
        return false;
    }
}
