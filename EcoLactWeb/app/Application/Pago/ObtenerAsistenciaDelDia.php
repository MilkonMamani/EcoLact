<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\AsistenciaDelDiaQuery;
use DateTimeImmutable;

/** Datos de la pantalla "Asistencia de hoy": quién marcó (o no) en una fecha dada. */
final class ObtenerAsistenciaDelDia
{
    public function __construct(private readonly AsistenciaDelDiaQuery $query) {}

    public function execute(DateTimeImmutable $fecha, ?string $busqueda = null, ?int $rolId = null, int $porPagina = 20): AsistenciaDelDiaData
    {
        $busqueda = $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null;
        $fecha = $fecha->setTime(0, 0);

        return new AsistenciaDelDiaData(
            resumen: $this->query->resumen($fecha, $busqueda, $rolId),
            filas: $this->query->paginar($fecha, $busqueda, $rolId, $porPagina),
        );
    }
}
