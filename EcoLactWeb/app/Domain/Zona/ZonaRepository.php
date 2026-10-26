<?php

namespace App\Domain\Zona;

interface ZonaRepository
{
    public function guardar(Zona $zona): Zona;

    public function actualizar(Zona $zona): Zona;

    public function buscarPorId(int $id): ?Zona;

    public function eliminar(int $id): void;

    /**
     * Cuántos registros de cada tipo dependen de la zona (rutas,
     * proveedores, acopiadores, historiales, entregas, comunicados),
     * indexado por etiqueta legible. Una zona solo puede eliminarse si
     * todos los conteos son 0.
     *
     * @return array<string, int>
     */
    public function contarDependencias(int $id): array;

    public function buscarPorNombre(string $nombre): ?Zona;

    /** Color del mapa y delimitación operativa (null la quita). */
    public function guardarDelimitacion(int $zonaId, string $colorMapa, ?DelimitacionZona $delimitacion): void;

    /**
     * @return Zona[]
     */
    public function listar(): array;

    /**
     * @return Zona[]
     */
    public function listarActivas(): array;

    /**
     * Delimitaciones operativas guardadas, de TODAS las zonas (activas o no),
     * ordenadas por nombre de zona. Una zona sin polígono, o con uno que ya
     * no se puede leer, no aparece.
     *
     * @return array<int, DelimitacionZona> zona_id => delimitación
     */
    public function listarDelimitaciones(): array;
}
