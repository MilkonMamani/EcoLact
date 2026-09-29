<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\FilaAsistenciaDelDia;
use App\Application\Pago\ResumenAsistenciaDelDia;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AsistenciaDelDiaQuery
{
    /**
     * Trabajadores ACTIVOS y vigentes en $fecha cuyo rol marca asistencia,
     * con su jornada de ese día si existe. Ordenados por nombre, paginados.
     *
     * @return LengthAwarePaginator<int, FilaAsistenciaDelDia>
     */
    public function paginar(DateTimeImmutable $fecha, ?string $busqueda, ?int $rolId, int $porPagina, string $nombrePagina = 'pagina'): LengthAwarePaginator;

    /** Contadores del mismo filtro completo (no solo de una página). */
    public function resumen(DateTimeImmutable $fecha, ?string $busqueda, ?int $rolId): ResumenAsistenciaDelDia;
}
