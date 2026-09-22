<?php

namespace App\Application\Zona;

/**
 * @phpstan-type Fila array{id: int, nombre: string, descripcion: ?string, proveedores: int, acopiadores: int, estado: string}
 */
final readonly class ZonasListadoData
{
    /**
     * @param  array{activas: int, inactivas: int, proveedoresAsignados: int, acopiadoresAsignados: int}  $kpis
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $filas,
    ) {}
}
