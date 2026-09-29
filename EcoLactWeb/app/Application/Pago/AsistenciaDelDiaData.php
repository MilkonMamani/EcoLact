<?php

namespace App\Application\Pago;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Pantalla "Asistencia de hoy": una página de filas y el resumen de todo el filtro. */
final readonly class AsistenciaDelDiaData
{
    /** @param  LengthAwarePaginator<int, FilaAsistenciaDelDia>  $filas */
    public function __construct(
        public ResumenAsistenciaDelDia $resumen,
        public LengthAwarePaginator $filas,
    ) {}
}
