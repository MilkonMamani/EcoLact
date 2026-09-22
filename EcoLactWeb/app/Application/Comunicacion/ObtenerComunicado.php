<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Comunicado;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;

final class ObtenerComunicado
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
    ) {}

    public function execute(int $comunicadoId): ?Comunicado
    {
        return $this->comunicados->buscarPorId($comunicadoId);
    }
}
