<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;

final class ObtenerPreciosLeche
{
    public function __construct(
        private readonly PrecioLecheRepository $preciosLeche,
    ) {}

    /**
     * @return PrecioLeche[]
     */
    public function execute(): array
    {
        return $this->preciosLeche->listar();
    }
}
