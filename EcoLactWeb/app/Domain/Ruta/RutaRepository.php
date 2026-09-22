<?php

namespace App\Domain\Ruta;

interface RutaRepository
{
    public function guardar(Ruta $ruta): Ruta;

    public function actualizar(Ruta $ruta): Ruta;

    public function buscarPorId(int $id): ?Ruta;

    public function buscarPorZonaYNombre(int $zonaId, string $nombre): ?Ruta;

    /**
     * @return Ruta[]
     */
    public function listarPorZona(int $zonaId): array;

    /**
     * @return Ruta[]
     */
    public function listarActivasPorZona(int $zonaId): array;

    /**
     * Todas las rutas activas de todas las zonas, ordenadas por nombre:
     * alimenta el selector de rutas del modal de asignación con una sola
     * consulta.
     *
     * @return Ruta[]
     */
    public function listarActivas(): array;
}
