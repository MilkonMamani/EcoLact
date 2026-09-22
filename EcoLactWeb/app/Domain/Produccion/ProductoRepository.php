<?php

namespace App\Domain\Produccion;

interface ProductoRepository
{
    public function guardar(Producto $producto): Producto;

    public function actualizar(Producto $producto): Producto;

    public function buscarPorId(int $id): ?Producto;

    public function buscarPorNombre(string $nombre): ?Producto;

    /**
     * @return Producto[]
     */
    public function listarActivos(): array;

    /**
     * @return Producto[]
     */
    public function listar(): array;

    /**
     * Productos activos que tienen una receta ACTIVA (los que se pueden producir).
     *
     * @return Producto[]
     */
    public function listarConRecetaActiva(): array;

    /**
     * Cuántos productos usan esta ruta de imagen (para no borrar un archivo en uso).
     */
    public function contarConImagen(string $ruta): int;
}
