<?php

namespace App\Application\Calidad;

use Carbon\CarbonImmutable;

/**
 * Universo de entregas que se ofrecen para muestreo, por fecha de la ENTREGA:
 * "hoy" (por defecto), "ayer" o "semana" (últimos 7 días, incluido hoy).
 */
final class RangoMuestreo
{
    private function __construct(
        public readonly CarbonImmutable $desde,
        public readonly CarbonImmutable $hasta,
    ) {}

    public static function resolver(?string $fecha, ?CarbonImmutable $ahora = null): self
    {
        $hoy = ($ahora ?? CarbonImmutable::now(config('app.timezone')))->startOfDay();

        return match ($fecha) {
            'ayer' => new self($hoy->subDay(), $hoy->subDay()->endOfDay()),
            'semana' => new self($hoy->subDays(6), $hoy->endOfDay()),
            default => new self($hoy, $hoy->endOfDay()),
        };
    }
}
