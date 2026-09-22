<?php

namespace App\Domain\Comunicacion\Repositories;

use App\Domain\Comunicacion\Notificacion;

interface NotificacionRepository
{
    public function guardar(Notificacion $notificacion): Notificacion;

    public function buscarPorId(int $id): ?Notificacion;

    /**
     * @return Notificacion[]
     */
    public function listarParaUsuario(int $userId): array;

    public function marcarComoLeida(int $id): Notificacion;
}
