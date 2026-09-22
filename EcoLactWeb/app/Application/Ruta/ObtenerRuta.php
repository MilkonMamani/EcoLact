<?php

namespace App\Application\Ruta;

use App\Domain\Ruta\Ruta;
use App\Domain\Ruta\RutaRepository;
use DomainException;

final class ObtenerRuta
{
    public function __construct(
        private readonly RutaRepository $rutas,
    ) {}

    public function execute(int $rutaId): Ruta
    {
        return $this->rutas->buscarPorId($rutaId)
            ?? throw new DomainException('La ruta indicada no existe.');
    }
}
