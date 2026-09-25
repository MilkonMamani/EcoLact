<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\AsistenciaDelDiaQuery;
use DateTimeImmutable;

/** Datos de la pantalla "Control de asistencia": quién marcó (o no) en una fecha dada. */
final class ObtenerAsistenciaDelDia
{
    public function __construct(private readonly AsistenciaDelDiaQuery $query) {}

    /**
     * @return FilaAsistenciaDelDia[]
     */
    public function execute(DateTimeImmutable $fecha, ?string $busqueda = null, ?int $rolId = null): array
    {
        $busqueda = $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null;

        return $this->query->obtener($fecha->setTime(0, 0), $busqueda, $rolId);
    }
}
