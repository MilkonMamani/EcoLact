<?php

namespace App\Domain\Produccion;

interface InsumoRepository
{
    public function guardar(Insumo $insumo): Insumo;

    public function actualizar(Insumo $insumo): Insumo;

    public function buscarPorId(int $id): ?Insumo;

    /**
     * Igual que buscarPorId pero bloquea la fila (SELECT ... FOR UPDATE)
     * dentro de la transacción en curso: es lo que impide que dos usuarios
     * consuman a la vez el mismo saldo y lo dejen negativo.
     */
    public function bloquearPorId(int $id): ?Insumo;

    public function buscarPorNombre(string $nombre): ?Insumo;

    /**
     * Busca un insumo del sistema por su código estable (ej. LECHE).
     */
    public function buscarPorCodigo(string $codigo): ?Insumo;

    /**
     * @return Insumo[]
     */
    public function listarActivos(): array;

    /**
     * @return Insumo[]
     */
    public function listarBajoMinimo(): array;

    /**
     * @return Insumo[]
     */
    public function listar(): array;
}
