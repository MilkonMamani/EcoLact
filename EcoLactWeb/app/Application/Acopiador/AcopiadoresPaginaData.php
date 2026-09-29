<?php

namespace App\Application\Acopiador;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pantalla principal de Acopiadores (admin): una página de acopiadores
 * paginada en el servidor más las zonas para filtrar y para reactivar.
 *
 * @phpstan-type FilaPagina array{id: int, nombre: string, dni: string, telefono: ?string, zonaNombre: string, entregas: int, estado: string}
 */
final readonly class AcopiadoresPaginaData
{
    /**
     * @param  list<array{id: int, nombre: string}>  $zonas  todas las zonas (filtro del listado)
     * @param  list<array{id: int, nombre: string}>  $zonasActivas  zonas a las que se puede asignar o reincorporar
     * @param  LengthAwarePaginator<int, FilaPagina>  $filas
     */
    public function __construct(
        public array $zonas,
        public array $zonasActivas,
        public LengthAwarePaginator $filas,
    ) {}
}
