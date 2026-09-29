<?php

namespace App\Application\Shared;

use DateTimeImmutable;

/**
 * Fecha "de calendario" del negocio. Lo usan los casos de uso que fijan una
 * fecha sin que el usuario la elija (p. ej. la fecha de ingreso de un
 * trabajador = el día en que se registró), para que dependa del servidor y
 * de la zona horaria de la planta, nunca del navegador ni de UTC.
 */
interface Reloj
{
    /** Hoy, a las 00:00, en la zona horaria del negocio. */
    public function hoy(): DateTimeImmutable;

    /** Fecha y hora actuales en la zona horaria del negocio. */
    public function ahora(): DateTimeImmutable;
}
