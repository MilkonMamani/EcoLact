<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider).
 * VENTAS es quien registra y consulta las ventas de producto. El PROVEEDOR
 * solo puede ver sus propias compras.
 */
class VentaProductoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esVentas() || $user->esProveedor();
    }

    /**
     * A diferencia de viewAny (que también deja pasar a un PROVEEDOR para
     * ver SUS PROPIAS compras vía porProveedor), el listado general
     * desacotado de TODAS las ventas es exclusivo de VENTAS/ADMINISTRADOR.
     */
    public function viewGeneral(User $user): bool
    {
        return $user->esVentas();
    }

    public function view(User $user, VentaProductoModel $venta): bool
    {
        if ($user->esVentas()) {
            return true;
        }

        return $user->esProveedor() && $user->proveedor !== null && $user->proveedor->id === $venta->proveedor_id;
    }

    public function create(User $user): bool
    {
        return $user->esVentas();
    }
}
