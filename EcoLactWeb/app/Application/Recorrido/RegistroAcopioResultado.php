<?php

namespace App\Application\Recorrido;

use App\Domain\Entrega\Entrega;
use App\Domain\Recorrido\RecorridoDetalle;

final readonly class RegistroAcopioResultado
{
    public function __construct(
        public Entrega $entrega,
        public RecorridoDetalle $parada,
    ) {}
}
