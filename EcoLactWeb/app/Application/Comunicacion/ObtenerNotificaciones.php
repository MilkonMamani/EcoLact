<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;

final class ObtenerNotificaciones
{
    public function __construct(
        private readonly NotificacionRepository $notificaciones,
    ) {}

    /**
     * @return Notificacion[]
     */
    public function execute(int $userId): array
    {
        return $this->notificaciones->listarParaUsuario($userId);
    }
}
