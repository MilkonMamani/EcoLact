<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider).
 * VENTAS administra el inventario de productos terminados (recibe lo que
 * planta entrega y descuenta lo vendido). Esta Policy gobierna
 * específicamente la pantalla UNIFICADA de Stock (productos + insumos
 * mezclados) de Web\Produccion\StockController, exclusiva de
 * ADMINISTRADOR/VENTAS: ENCARGADO_PLANTA consulta su propio stock de
 * productos (solo lectura) a través de Web\Planta\ProductoController, que
 * no pasa por esta Policy (ya está acotado por el middleware role:
 * ENCARGADO_PLANTA de su propia ruta).
 */
class ProductoStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esVentas();
    }

    public function view(User $user, ProductoMovimientoModel $stock): bool
    {
        return $user->esVentas();
    }

    public function create(User $user): bool
    {
        return $user->esVentas();
    }
}
