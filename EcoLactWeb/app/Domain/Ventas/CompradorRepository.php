<?php

namespace App\Domain\Ventas;

interface CompradorRepository
{
    public function guardar(Comprador $comprador): Comprador;

    public function buscarPorId(int $id): ?Comprador;

    /**
     * @return Comprador[]
     */
    public function listarActivos(): array;
}
