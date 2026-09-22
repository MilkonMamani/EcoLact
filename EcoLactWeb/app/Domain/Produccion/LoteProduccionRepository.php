<?php

namespace App\Domain\Produccion;

interface LoteProduccionRepository
{
    public function guardar(LoteProduccion $lote): LoteProduccion;

    public function actualizar(LoteProduccion $lote): LoteProduccion;

    public function buscarPorId(int $id): ?LoteProduccion;

    /**
     * Igual que buscarPorId pero bloquea la fila (SELECT ... FOR UPDATE)
     * dentro de la transacción en curso, para que dos usuarios no puedan
     * finalizar el mismo lote a la vez.
     */
    public function bloquearPorId(int $id): ?LoteProduccion;

    public function buscarPorCodigo(string $codigo): ?LoteProduccion;

    /**
     * @return LoteProduccion[]
     */
    public function listarPorEstado(string $estado): array;

    /**
     * @return LoteProduccion[]
     */
    public function listar(): array;
}
