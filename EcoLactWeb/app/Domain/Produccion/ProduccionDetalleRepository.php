<?php

namespace App\Domain\Produccion;

interface ProduccionDetalleRepository
{
    public function guardar(ProduccionDetalle $detalle): ProduccionDetalle;

    public function actualizar(ProduccionDetalle $detalle): ProduccionDetalle;

    /**
     * @return ProduccionDetalle[]
     */
    public function listarPorLote(int $loteProduccionId): array;
}
