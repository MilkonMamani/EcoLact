<?php

namespace App\Application\PersonalVentas;

use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;

final class ObtenerListaPersonalVentas
{
    public function __construct(
        private readonly PersonalVentasRepository $personal,
    ) {}

    /**
     * @return PersonalVentas[]
     */
    public function execute(): array
    {
        return $this->personal->listar();
    }
}
