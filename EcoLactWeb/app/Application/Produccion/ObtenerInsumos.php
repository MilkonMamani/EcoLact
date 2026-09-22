<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;

final class ObtenerInsumos
{
    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    /**
     * @return Insumo[]
     */
    public function execute(bool $soloBajoMinimo = false): array
    {
        return $soloBajoMinimo
            ? $this->insumos->listarBajoMinimo()
            : $this->insumos->listarActivos();
    }
}
