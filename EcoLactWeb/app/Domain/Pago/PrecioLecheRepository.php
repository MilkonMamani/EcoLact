<?php

namespace App\Domain\Pago;

use DateTimeImmutable;

interface PrecioLecheRepository
{
    public function guardar(PrecioLeche $precioLeche): PrecioLeche;

    public function actualizar(PrecioLeche $precioLeche): PrecioLeche;

    public function buscarPorId(int $id): ?PrecioLeche;

    public function buscarVigente(DateTimeImmutable $fecha): ?PrecioLeche;

    /**
     * @return PrecioLeche[]
     */
    public function listar(): array;
}
