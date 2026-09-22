<?php

namespace App\Application\Acopio;

use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;

final class ObtenerEntrega
{
    public function __construct(
        private readonly EntregaRepository $entregas,
    ) {}

    public function execute(int $entregaId): ?Entrega
    {
        return $this->entregas->buscarPorId($entregaId);
    }
}
