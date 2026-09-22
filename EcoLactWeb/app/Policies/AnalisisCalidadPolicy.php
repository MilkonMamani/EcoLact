<?php

namespace App\Policies;

use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Models\User;

/**
 * ADMINISTRADOR consulta todos los análisis. CALIDAD es quien realiza el
 * análisis técnico (crear/finalizar), sin estar acotado a ninguna zona: un
 * analista puede controlar la calidad de cualquier zona. El ACOPIADOR ya NO
 * analiza (solo recolecta leche, ver EntregaPolicy): conserva acceso de
 * SOLO LECTURA a los análisis de su propia zona, para ver el resultado de
 * lo que él recolectó. El PROVEEDOR solo ve los de sus propias entregas.
 *
 * update() es la excepción explícita a Gate::before: un análisis
 * CONFIRMADO es inmutable para TODOS, ADMINISTRADOR incluido. AppServiceProvider
 * deja pasar esta ability al método de la Policy en vez de concederla de
 * forma global, precisamente para no romper esa invariante de Domain
 * (AnalisisCalidad::confirmar() ya la protege; esto es la misma regla
 * repetida como "defense in depth" en la capa de autorización).
 */
class AnalisisCalidadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esCalidad() || $user->esAcopiador() || $user->esProveedor();
    }

    /**
     * Registrar un análisis (RegistrarAnalisisCalidad) es una operación
     * exclusiva de CALIDAD, sin restricción de zona: el analista elige a
     * qué proveedor de cuál zona le hace el control de calidad.
     */
    public function create(User $user): bool
    {
        return $user->esCalidad();
    }

    public function view(User $user, AnalisisCalidadModel $analisis): bool
    {
        if ($user->esCalidad()) {
            return true;
        }

        if ($user->esAcopiador()) {
            return $user->acopiador !== null && $user->acopiador->zona_id === $analisis->entrega?->zona_id;
        }

        if ($user->esProveedor()) {
            return $user->proveedor !== null && $user->proveedor->id === $analisis->entrega?->proveedor_id;
        }

        return false;
    }

    public function update(User $user, AnalisisCalidadModel $analisis): bool
    {
        if ($analisis->estado === EstadoAnalisis::CONFIRMADO->value) {
            return false;
        }

        if ($user->esAdministrador()) {
            return true;
        }

        return $user->esCalidad();
    }
}
