<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\FilaAsistenciaDelDia;
use DateTimeImmutable;

interface AsistenciaDelDiaQuery
{
    /**
     * Trabajadores ACTIVOS y vigentes en $fecha, con su jornada de ese día si
     * existe. Ordenados por nombre.
     *
     * @return FilaAsistenciaDelDia[]
     */
    public function obtener(DateTimeImmutable $fecha, ?string $busqueda, ?int $rolId): array;
}
