<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ReglaCalidadModel;
use App\Models\User;

/**
 * Configuración exclusiva de ADMINISTRADOR (Gate::before). A diferencia de
 * ParametroCalidad, los límites de una regla (valor_minimo/valor_maximo)
 * son configuración de negocio que el acopiador no necesita ver para hacer
 * su trabajo: la clasificación ACEPTADA/OBSERVADA/RECHAZADA la calcula el
 * servidor (CalcularResultadoCalidad); no hay necesidad funcional
 * demostrada para exponérsela.
 */
class ReglaCalidadPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ReglaCalidadModel $regla): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReglaCalidadModel $regla): bool
    {
        return false;
    }
}
