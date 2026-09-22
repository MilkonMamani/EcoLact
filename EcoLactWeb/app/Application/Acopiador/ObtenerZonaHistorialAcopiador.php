<?php

namespace App\Application\Acopiador;

use App\Domain\Acopiador\AcopiadorZonaHistorial;
use App\Domain\Acopiador\AcopiadorZonaHistorialRepository;

final class ObtenerZonaHistorialAcopiador
{
    public function __construct(
        private readonly AcopiadorZonaHistorialRepository $historiales,
    ) {}

    /**
     * @return AcopiadorZonaHistorial[]
     */
    public function execute(int $acopiadorId): array
    {
        return $this->historiales->listarPorAcopiador($acopiadorId);
    }
}
