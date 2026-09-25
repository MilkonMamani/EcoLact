<?php

namespace App\Application\Acopio;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pantalla principal de Acopio: una página de entregas paginada en el
 * servidor más las opciones de los filtros. No lleva indicadores.
 *
 * @phpstan-import-type Fila from EntregasListadoData
 */
final readonly class EntregasPaginaData
{
    /**
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<array{id: int, nombre: string}>  $acopiadores
     * @param  LengthAwarePaginator<int, Fila>  $filas
     */
    public function __construct(
        public array $zonas,
        public array $acopiadores,
        public LengthAwarePaginator $filas,
    ) {}
}
