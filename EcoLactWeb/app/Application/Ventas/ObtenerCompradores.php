<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\Comprador;
use App\Domain\Ventas\CompradorRepository;

final class ObtenerCompradores
{
    public function __construct(
        private readonly CompradorRepository $compradores,
    ) {}

    /**
     * @return Comprador[]
     */
    public function execute(): array
    {
        return $this->compradores->listarActivos();
    }
}
