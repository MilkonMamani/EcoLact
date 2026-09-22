<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionRepository;

final class ObtenerLotesProduccion
{
    public function __construct(
        private readonly LoteProduccionRepository $lotes,
    ) {}

    /**
     * Con $estado, acota a lotes en ese estado; sin él, lista todos.
     *
     * @return LoteProduccion[]
     */
    public function execute(?string $estado = null): array
    {
        return $estado !== null
            ? $this->lotes->listarPorEstado($estado)
            : $this->lotes->listar();
    }
}
