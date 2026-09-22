<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Models\User;

/**
 * ADMINISTRADOR ve cualquier entrega (Gate::before). El ACOPIADOR opera
 * sobre entregas de su propia zona; el PROVEEDOR solo ve las suyas. CALIDAD
 * ve (solo lectura) las entregas de CUALQUIER zona: necesita revisar el
 * acopio del día en cualquier zona para elegir a qué proveedor le hace el
 * control de calidad, sin estar restringido a una zona propia.
 *
 * La comprobación definitiva de acopiador ACTIVO, proveedor ACTIVO, misma
 * zona y litros > 0 sigue perteneciendo al Use Case RegistrarEntrega — esta
 * Policy solo decide si el usuario puede INTENTAR la operación, no repite
 * esas reglas de negocio.
 */
class EntregaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esCalidad() || $user->esAcopiador() || $user->esProveedor();
    }

    public function view(User $user, EntregaModel $entrega): bool
    {
        if ($user->esCalidad()) {
            return true;
        }

        if ($user->esAcopiador()) {
            return $user->acopiador !== null && $user->acopiador->zona_id === $entrega->zona_id;
        }

        if ($user->esProveedor()) {
            return $user->proveedor !== null && $user->proveedor->id === $entrega->proveedor_id;
        }

        return false;
    }

    /**
     * ACOPIADOR registra sus entregas normales. CALIDAD también puede
     * registrar (vía RegistrarEntregaMuestreada, nunca RegistrarEntrega)
     * la excepción de una muestra que analiza directamente del proveedor,
     * antes de que llegue al acopiador — típicamente cuando el resultado va
     * a ser RECHAZADA y esa leche nunca se acopia.
     */
    public function create(User $user): bool
    {
        return $user->esAcopiador() || $user->esCalidad();
    }

    /**
     * Editar una entrega ya registrada es exclusivo de ADMINISTRADOR
     * (Gate::before); nadie más puede intentarlo. ActualizarEntrega además
     * bloquea la edición una vez que la entrega deja de estar
     * PENDIENTE_ANALISIS (ya puede tener análisis, valorización o
     * liquidación asociados).
     */
    public function update(User $user, EntregaModel $entrega): bool
    {
        return false;
    }
}
