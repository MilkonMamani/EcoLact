<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionRepository;

final class ObtenerLoteProduccion
{
    public function __construct(
        private readonly LoteProduccionRepository $lotes,
    ) {}

    public function execute(int $loteId): ?LoteProduccion
    {
        return $this->lotes->buscarPorId($loteId);
    }
}
