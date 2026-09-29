<?php

namespace App\Infrastructure\Tiempo;

use App\Application\Shared\Reloj;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/** Reloj del servidor en la zona horaria del negocio (config app.zona_horaria_negocio). Respeta Carbon::setTestNow / travelTo. */
final class RelojLaravel implements Reloj
{
    public function hoy(): DateTimeImmutable
    {
        return CarbonImmutable::now(config('app.zona_horaria_negocio'))->startOfDay()->toDateTimeImmutable();
    }

    public function ahora(): DateTimeImmutable
    {
        return CarbonImmutable::now(config('app.zona_horaria_negocio'))->toDateTimeImmutable();
    }
}
