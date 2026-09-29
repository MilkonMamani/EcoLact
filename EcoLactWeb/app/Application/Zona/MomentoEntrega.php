<?php

namespace App\Application\Zona;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Etiqueta corta de "última entrega" en la hora del negocio: "Hoy 06:42",
 * "Ayer 17:30" o la fecha (24/09/2026) si fue antes.
 */
final class MomentoEntrega
{
    /**
     * @param  string  $fechaHora  valor de entregas.fecha_hora (zona horaria de la app)
     * @param  DateTimeImmutable  $hoy  día de hoy en la zona del negocio (Reloj::hoy())
     */
    public static function etiqueta(string $fechaHora, DateTimeImmutable $hoy): string
    {
        $momento = (new DateTimeImmutable($fechaHora, new DateTimeZone(config('app.timezone'))))
            ->setTimezone(new DateTimeZone(config('app.zona_horaria_negocio')));
        $dia = $momento->format('Y-m-d');

        return match ($dia) {
            $hoy->format('Y-m-d') => 'Hoy '.$momento->format('H:i'),
            $hoy->modify('-1 day')->format('Y-m-d') => 'Ayer '.$momento->format('H:i'),
            default => $momento->format('d/m/Y'),
        };
    }
}
