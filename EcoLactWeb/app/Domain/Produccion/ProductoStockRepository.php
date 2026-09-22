<?php

namespace App\Domain\Produccion;

/**
 * Consulta de saldos de productos terminados. Es de SOLO LECTURA: el saldo
 * siempre se calcula sumando los movimientos con signo; para cambiarlo se
 * registra un ProductoMovimiento.
 */
interface ProductoStockRepository
{
    /**
     * Saldo actual del producto, o null si nunca tuvo movimientos.
     */
    public function buscarPorProductoId(int $productoId): ?ProductoStock;

    /**
     * Saldos de todos los productos que tienen movimientos.
     *
     * @return ProductoStock[]
     */
    public function listarTodos(): array;

    /**
     * Bloquea la fila del producto (SELECT ... FOR UPDATE) dentro de la
     * transacción en curso, para serializar operaciones que validan saldo
     * antes de descontar (ventas, ajustes).
     */
    public function bloquearProducto(int $productoId): void;
}
