<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Models\User;

/**
 * ADMINISTRADOR tiene acceso global vía Gate::before (AppServiceProvider).
 * Esta Policy resuelve además las reglas de CALIDAD, ACOPIADOR y PROVEEDOR.
 */
class ProveedorPolicy
{
    /**
     * Listar proveedores: CALIDAD ve todos, de cualquier zona (necesita
     * elegir a cualquier proveedor como destinatario de un comunicado o
     * revisar su historial de advertencias). El ACOPIADOR puede pedir el
     * listado de su propia zona (el Use Case ObtenerProveedores exige
     * zona_id explícito, así que el filtrado real ya está acotado ahí). El
     * PROVEEDOR no administra otros proveedores.
     */
    public function viewAny(User $user): bool
    {
        return $user->esCalidad() || $user->esAcopiador();
    }

    /**
     * Ver un proveedor puntual: CALIDAD puede ver cualquiera; el ACOPIADOR
     * solo si pertenece a su propia zona; el PROVEEDOR solo si es su propio
     * registro.
     */
    public function view(User $user, ProveedorModel $proveedor): bool
    {
        if ($user->esCalidad()) {
            return true;
        }

        if ($user->esAcopiador()) {
            return $user->acopiador !== null && $user->acopiador->zona_id === $proveedor->zona_id;
        }

        if ($user->esProveedor()) {
            return $proveedor->user_id === $user->id;
        }

        return false;
    }

    /**
     * Crear/editar proveedores es exclusivo de ADMINISTRADOR (ya cubierto
     * por Gate::before); nadie más puede.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProveedorModel $proveedor): bool
    {
        return false;
    }
}
