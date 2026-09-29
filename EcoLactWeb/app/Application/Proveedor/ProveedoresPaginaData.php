<?php

namespace App\Application\Proveedor;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pantalla principal de Proveedores (admin): una página de proveedores
 * paginada en el servidor más las opciones de los filtros.
 *
 * @phpstan-type FilaPagina array{id: int, nombre: string, dni: string, telefono: ?string, zonaNombre: ?string, ultimaEntrega: ?string, totalLitros: float, estado: string}
 */
final readonly class ProveedoresPaginaData
{
    /**
     * @param  list<array{id: int, nombre: string}>  $zonas  todas las zonas (filtro del listado)
     * @param  LengthAwarePaginator<int, FilaPagina>  $filas
     * @param  list<array{id: int, nombre: string, rutas: list<array{id: int, nombre: string}>}>  $zonasReincorporacion  zonas activas con sus rutas activas (modal "Reactivar proveedor")
     */
    public function __construct(
        public array $zonas,
        public LengthAwarePaginator $filas,
        public array $zonasReincorporacion = [],
    ) {}
}
