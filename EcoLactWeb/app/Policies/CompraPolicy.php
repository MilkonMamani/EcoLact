<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use App\Models\User;

/**
 * Inventario > Compras y gastos es exclusivo de ADMINISTRADOR (acceso global
 * vía Gate::before). Cada acción tiene su propia habilidad para poder
 * delegarla después sin tocar los controladores. Ningún otro rol accede hoy:
 * el encargado de planta registra sus compras de insumos desde la app con
 * InsumoPolicy::create, que pasa por el mismo flujo (RegistrarCompra).
 *
 * `anular` existe pero no hay operación de anulación: revertir una compra
 * pagada necesita un movimiento compensatorio en Caja que aún no se definió.
 */
class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, CompraInsumoModel $compra): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function registrarPago(User $user, CompraInsumoModel $compra): bool
    {
        return false;
    }

    public function recibir(User $user, CompraInsumoModel $compra): bool
    {
        return false;
    }

    public function adjuntarComprobante(User $user, CompraInsumoModel $compra): bool
    {
        return false;
    }

    public function verComprobante(User $user, CompraInsumoModel $compra): bool
    {
        return false;
    }

    public function anular(User $user, CompraInsumoModel $compra): bool
    {
        return false;
    }
}
