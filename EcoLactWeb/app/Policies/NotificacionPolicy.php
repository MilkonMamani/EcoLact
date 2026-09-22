<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Models\NotificacionModel;
use App\Models\User;

/**
 * ObtenerNotificaciones ya solo consulta las notificaciones del usuario
 * AUTENTICADO (nunca un user_id arbitrario), así que index() no necesita
 * Policy. Marcar como leída SÍ opera sobre una notificación puntual: un
 * ACOPIADOR/PROVEEDOR solo puede marcar como leída una notificación propia.
 * ADMINISTRADOR tiene acceso global vía Gate::before.
 */
class NotificacionPolicy
{
    public function update(User $user, NotificacionModel $notificacion): bool
    {
        return $notificacion->user_id === $user->id;
    }
}
