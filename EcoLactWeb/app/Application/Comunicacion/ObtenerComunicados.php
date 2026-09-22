<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Comunicado;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;

final class ObtenerComunicados
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
    ) {}

    /**
     * Con $userId, acota a los comunicados dirigidos a ese usuario (uso de
     * ACOPIADOR/PROVEEDOR); sin él, lista todos (uso de ADMINISTRADOR).
     *
     * @return Comunicado[]
     */
    public function execute(?int $userId = null): array
    {
        return $userId !== null
            ? $this->comunicados->listarParaUsuario($userId)
            : $this->comunicados->listar();
    }
}
