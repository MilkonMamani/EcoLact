<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider).
 * Además, ENCARGADO_PLANTA opera la producción directamente (crear lotes,
 * registrar el resultado y el consumo de insumos). No se otorga acceso a
 * ACOPIADOR, PROVEEDOR ni VENTAS: no existe un requerimiento real
 * documentado para ello.
 */
class LoteProduccionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function view(User $user, LoteProduccionModel $lote): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function create(User $user): bool
    {
        return $user->esEncargadoPlanta();
    }

    public function update(User $user, LoteProduccionModel $lote): bool
    {
        return $user->esEncargadoPlanta();
    }
}
