<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Models\User;

/**
 * Crear/editar parámetros de calidad es exclusivo de ADMINISTRADOR
 * (Gate::before). Se concede lectura al ACOPIADOR porque tiene una
 * necesidad funcional real: RegistrarAnalisisCalidad recibe los valores
 * medidos indexados por el código del parámetro, así que el acopiador
 * necesita saber qué parámetros existen para poder completarlos. El
 * PROVEEDOR no participa en el registro de análisis.
 */
class ParametroCalidadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAcopiador();
    }

    public function view(User $user, ParametroCalidadModel $parametro): bool
    {
        return $user->esAcopiador();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ParametroCalidadModel $parametro): bool
    {
        return false;
    }
}
