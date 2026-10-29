<?php

namespace App\Domain\Compra;

interface CategoriaCompraRepository
{
    public function buscarPorId(int $id): ?CategoriaCompra;

    public function buscarPorCodigo(string $codigo): ?CategoriaCompra;

    /**
     * @return list<CategoriaCompra> en su orden, activas e inactivas
     */
    public function listar(): array;
}
