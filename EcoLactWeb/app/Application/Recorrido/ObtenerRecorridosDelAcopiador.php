<?php

namespace App\Application\Recorrido;

use App\Domain\Recorrido\RecorridoAcopio;
use App\Domain\Recorrido\RecorridoAcopioRepository;

final class ObtenerRecorridosDelAcopiador
{
    public function __construct(
        private readonly RecorridoAcopioRepository $recorridos,
    ) {}

    /**
     * @return RecorridoAcopio[]
     */
    public function execute(int $acopiadorId): array
    {
        return $this->recorridos->listarPorAcopiador($acopiadorId);
    }
}
