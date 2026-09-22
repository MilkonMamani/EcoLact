<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

/**
 * Pagina en PHP el array `filas`/rows de un *ListadoData ya calculado por un
 * *ListadoQuery existente (que devuelve el listado completo vía ->get()).
 * No se modifica ningún Application/Query: el recorte ocurre solo en esta
 * capa de presentación JSON, igual para todos los listados admin.* de la API.
 */
trait PaginaFilas
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{0: list<array<string, mixed>>, 1: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginar(array $filas, Request $request): array
    {
        $total = count($filas);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min(max((int) $request->integer('page', 1), 1), $lastPage);

        $pagina = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return [
            array_values($pagina),
            [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }
}
