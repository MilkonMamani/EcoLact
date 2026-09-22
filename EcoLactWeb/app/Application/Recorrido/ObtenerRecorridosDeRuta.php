<?php

namespace App\Application\Recorrido;

use App\Domain\Recorrido\RecorridoAcopio;
use App\Domain\Recorrido\RecorridoAcopioRepository;

final class ObtenerRecorridosDeRuta
{
    public function __construct(
        private readonly RecorridoAcopioRepository $recorridos,
    ) {}

    /**
     * @return RecorridoAcopio[]
     */
    public function execute(int $rutaId): array
    {
        return $this->recorridos->listarPorRuta($rutaId);
    }
}
