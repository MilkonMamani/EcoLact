<?php

namespace App\Application\Acopio;

use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;

final class ObtenerEntregasProveedor
{
    public function __construct(
        private readonly EntregaRepository $entregas,
    ) {}

    /**
     * @return Entrega[]
     */
    public function execute(int $proveedorId): array
    {
        return $this->entregas->listarPorProveedor($proveedorId);
    }
}
