<?php

namespace App\Domain\Pago;

interface ValorizacionEntregaRepository
{
    public function guardar(ValorizacionEntrega $valorizacion): ValorizacionEntrega;

    public function buscarPorEntregaId(int $entregaId): ?ValorizacionEntrega;

    /**
     * @param  int[]  $entregaIds
     * @return ValorizacionEntrega[]
     */
    public function listarPorEntregaIds(array $entregaIds): array;
}
