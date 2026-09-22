<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;

final class ObtenerInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    public function execute(int $insumoId): ?Insumo
    {
        return $this->insumos->buscarPorId($insumoId);
    }
}
