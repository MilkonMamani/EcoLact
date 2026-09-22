<?php

namespace App\Application\PersonalVentas;

use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;

final class ObtenerPersonalVentas
{
    public function __construct(
        private readonly PersonalVentasRepository $personal,
    ) {}

    public function execute(int $personalId): ?PersonalVentas
    {
        return $this->personal->buscarPorId($personalId);
    }
}
