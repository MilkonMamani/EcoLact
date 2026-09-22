<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\RecorridoAcopioModel;
use App\Models\User;

/**
 * ADMINISTRADOR gestiona la asignación (crear/abrir un recorrido) desde el
 * módulo Zonas, vía Gate::before. El ACOPIADOR solo accede a los recorridos
 * que le pertenecen (recorrido.acopiador_id === su propio registro de
 * Acopiador) — nunca a uno ajeno, sin importar qué ID de recorrido reciba
 * la URL. Esta es la autorización real de "iniciar", "finalizar",
 * "identificar parada", "registrar acopio" y "marcar no entregó": todas
 * pasan por 'update' sobre el RecorridoAcopioModel concreto.
 */
class RecorridoAcopioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAcopiador();
    }

    public function view(User $user, RecorridoAcopioModel $recorrido): bool
    {
        return $user->esAcopiador()
            && $user->acopiador !== null
            && $user->acopiador->id === $recorrido->acopiador_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RecorridoAcopioModel $recorrido): bool
    {
        return $user->esAcopiador()
            && $user->acopiador !== null
            && $user->acopiador->id === $recorrido->acopiador_id;
    }
}
