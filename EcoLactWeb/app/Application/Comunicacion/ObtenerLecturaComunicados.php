<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Repositories\ComunicadoRepository;

final class ObtenerLecturaComunicados
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
    ) {}

    /**
     * @return array<int, bool> comunicado_id => leído
     */
    public function execute(int $userId): array
    {
        return $this->comunicados->listarLecturaParaUsuario($userId);
    }
}
