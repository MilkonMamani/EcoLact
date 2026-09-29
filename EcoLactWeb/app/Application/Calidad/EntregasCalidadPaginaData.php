<?php

namespace App\Application\Calidad;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pantalla Calidad > Análisis: una página de entregas (con su análisis si lo
 * tienen) paginada en el servidor más las opciones de los filtros. No lleva
 * indicadores.
 *
 * @phpstan-import-type Fila from EntregasCalidadListadoData
 */
final readonly class EntregasCalidadPaginaData
{
    /**
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<array{id: int, nombre: string}>  $analistas  id = users.id del analista (confirmado_por_user_id)
     * @param  LengthAwarePaginator<int, Fila>  $filas
     */
    public function __construct(
        public array $zonas,
        public array $analistas,
        public LengthAwarePaginator $filas,
    ) {}
}
