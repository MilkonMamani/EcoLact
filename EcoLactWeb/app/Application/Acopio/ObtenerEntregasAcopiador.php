<?php

namespace App\Application\Acopio;

use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;

final class ObtenerEntregasAcopiador
{
    public function __construct(
        private readonly EntregaRepository $entregas,
    ) {}

    /**
     * @return Entrega[]
     */
    public function execute(int $acopiadorId): array
    {
        return $this->entregas->listarPorAcopiador($acopiadorId);
    }
}
