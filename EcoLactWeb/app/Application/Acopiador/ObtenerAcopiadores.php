<?php

namespace App\Application\Acopiador;

use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;

final class ObtenerAcopiadores
{
    public function __construct(
        private readonly AcopiadorRepository $acopiadores,
    ) {}

    /**
     * Lista acopiadores. Con $zonaId, acota a esa zona; sin él, lista todos
     * (uso pensado para ADMINISTRADOR, único rol con acceso a este listado
     * completo según AcopiadorPolicy).
     *
     * @return Acopiador[]
     */
    public function execute(?int $zonaId = null): array
    {
        return $zonaId !== null
            ? $this->acopiadores->listarPorZona($zonaId)
            : $this->acopiadores->listar();
    }
}
