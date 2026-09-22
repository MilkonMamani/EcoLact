<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Models\User;

/**
 * Los pagos pendientes y realizados (con sus comprobantes) son exclusivos de
 * ADMINISTRADOR (Gate::before). El proveedor ve su pago desde su propio
 * portal (PagoProveedorPolicy), no desde este módulo.
 */
class ObligacionPagoPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ObligacionPagoModel $obligacion): bool
    {
        return false;
    }

    public function pay(User $user, ?ObligacionPagoModel $obligacion = null): bool
    {
        return false;
    }

    public function generate(User $user): bool
    {
        return false;
    }
}
