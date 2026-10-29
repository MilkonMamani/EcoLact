<?php

namespace App\Domain\Compra;

interface ComprobanteCompraRepository
{
    public function guardar(ComprobanteCompra $comprobante): ComprobanteCompra;

    public function buscarPorId(int $id): ?ComprobanteCompra;

    /**
     * @return list<ComprobanteCompra> en el orden en que se adjuntaron
     */
    public function listarPorCompra(int $compraId): array;
}
