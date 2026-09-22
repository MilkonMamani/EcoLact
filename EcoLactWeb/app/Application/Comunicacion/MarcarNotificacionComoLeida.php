<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use DomainException;

/**
 * Marca una notificación como leída, verificando que pertenezca al usuario
 * que la solicita. Esta comprobación se repite aquí como defensa en
 * profundidad aunque el Controller ya autorice vía Policy (mismo patrón que
 * AnalisisCalidad::confirmar() / AnalisisCalidadPolicy::update()): un
 * proveedor o acopiador nunca puede marcar como leída la notificación de
 * otro usuario, ni siquiera si esa comprobación de la capa HTTP fallara.
 */
final class MarcarNotificacionComoLeida
{
    public function __construct(
        private readonly NotificacionRepository $notificaciones,
    ) {}

    public function execute(int $notificacionId, int $userId): Notificacion
    {
        $notificacion = $this->notificaciones->buscarPorId($notificacionId);

        if ($notificacion === null) {
            throw new DomainException('La notificación indicada no existe.');
        }

        if ($notificacion->userId() !== $userId) {
            throw new DomainException('No puede marcar como leída una notificación de otro usuario.');
        }

        return $this->notificaciones->marcarComoLeida($notificacionId);
    }
}
