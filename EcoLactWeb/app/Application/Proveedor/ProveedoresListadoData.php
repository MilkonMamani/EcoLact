<?php

namespace App\Application\Proveedor;

/**
 * @phpstan-type Fila array{id: int, nombre: string, dni: string, zonaNombre: string, telefono: ?string, advertencias: int, estado: string}
 */
final readonly class ProveedoresListadoData
{
    /**
     * @param  array{activos: int, inactivos: int, zonasConProveedores: int, requierenSeguimiento: int}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $filas,
    ) {}
}
