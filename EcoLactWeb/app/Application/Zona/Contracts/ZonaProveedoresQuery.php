<?php

namespace App\Application\Zona\Contracts;

use App\Application\Zona\ProveedorAsignadoFila;

interface ZonaProveedoresQuery
{
    /**
     * Proveedores con asignación vigente en alguna ruta de la zona, por
     * ruta y orden de visita.
     *
     * @return list<ProveedorAsignadoFila>
     */
    public function deZona(int $zonaId): array;

    /**
     * Proveedores activos que todavía no están asignados a ninguna ruta.
     *
     * @return list<ProveedorAsignadoFila>
     */
    public function sinAsignar(): array;

    public function contarSinAsignar(): int;

    /**
     * Búsqueda de proveedores activos por nombre, apellidos o DNI (cada
     * palabra del texto debe coincidir con alguno de esos campos).
     *
     * @return list<ProveedorAsignadoFila>
     */
    public function buscar(string $texto, int $limite = 20): array;
}
