<?php

namespace App\Application\Acopiador;

use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;

final class ObtenerAcopiador
{
    public function __construct(
        private readonly AcopiadorRepository $acopiadores,
    ) {}

    public function execute(int $acopiadorId): ?Acopiador
    {
        return $this->acopiadores->buscarPorId($acopiadorId);
    }
}
