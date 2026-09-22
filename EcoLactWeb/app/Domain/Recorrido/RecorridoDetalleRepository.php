<?php

namespace App\Domain\Recorrido;

interface RecorridoDetalleRepository
{
    public function guardar(RecorridoDetalle $detalle): RecorridoDetalle;

    public function actualizar(RecorridoDetalle $detalle): RecorridoDetalle;

    public function buscarPorId(int $id): ?RecorridoDetalle;

    public function buscarPorRecorridoYProveedor(int $recorridoId, int $proveedorId): ?RecorridoDetalle;

    /**
     * @return RecorridoDetalle[] ordenados por orden_visita ascendente
     */
    public function listarPorRecorrido(int $recorridoId): array;
}
