<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\EstadoReceta;

interface RecetaRepository
{
    /**
     * Guarda una receta nueva junto con sus ingredientes.
     */
    public function guardar(Receta $receta): Receta;

    /**
     * Actualiza los datos de una receta y reemplaza sus ingredientes.
     */
    public function actualizar(Receta $receta): Receta;

    /**
     * Cambia el estado. Al activar, la receta pasa a ser la única activa del
     * producto (la base de datos lo garantiza con un índice único).
     */
    public function cambiarEstado(int $recetaId, EstadoReceta $estado): void;

    public function buscarPorId(int $id): ?Receta;

    public function buscarActivaPorProducto(int $productoId): ?Receta;

    public function versionMaximaDelProducto(int $productoId): int;

    /**
     * Una receta está en uso cuando alguna producción (lote) la utilizó.
     */
    public function estaEnUso(int $recetaId): bool;
}
