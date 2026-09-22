<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\ComunicadoModel;
use App\Models\User;

/**
 * ADMINISTRADOR gestiona comunicados (Gate::before). Cualquier otro
 * usuario solo puede ver un comunicado si figura como destinatario
 * registrado (comunicado_destinatarios), que es exactamente el mismo
 * criterio que ya usa CrearComunicado/listarParaUsuario en Application.
 */
class ComunicadoPolicy
{
    /**
     * Cualquier usuario autenticado puede pedir SU listado de comunicados;
     * el contenido real (todos para ADMINISTRADOR, solo los propios para
     * ACOPIADOR/PROVEEDOR) lo decide el parámetro que el Controller le pasa
     * a ObtenerComunicados, no esta Policy.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ComunicadoModel $comunicado): bool
    {
        return $comunicado->destinatarios()->where('user_id', $user->id)->exists();
    }

    /**
     * CALIDAD puede redactar comunicados (ej. proponer capacitaciones a un
     * proveedor a partir de los resultados de sus análisis), sin
     * restricción de zona: puede dirigirse a cualquier proveedor.
     */
    public function create(User $user): bool
    {
        return $user->esCalidad();
    }

    /**
     * Editar/publicar un comunicado es exclusivo de ADMINISTRADOR
     * (Gate::before). La regla real (solo mientras esté en BORRADOR) la
     * aplica ActualizarComunicado/PublicarComunicado, igual que
     * EntregaPolicy::update delega en ActualizarEntrega.
     */
    public function update(User $user, ComunicadoModel $comunicado): bool
    {
        return false;
    }
}
