<?php

namespace App\Application\Produccion;

use Carbon\CarbonImmutable;

final readonly class LotesProduccionFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?string $estado,
        public ?CarbonImmutable $fechaDesde,
        public ?CarbonImmutable $fechaHasta,
    ) {}

    public static function resolver(
        ?string $busqueda,
        ?string $estado,
        ?string $periodo,
        ?string $desde,
        ?string $hasta,
    ): self {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        [$fechaDesde, $fechaHasta] = match ($periodo) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'esta_semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde, $hoy->timezone)->startOfDay() : null,
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta, $hoy->timezone)->endOfDay() : null,
            ],
            default => [null, null],
        };

        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
            fechaDesde: $fechaDesde,
            fechaHasta: $fechaHasta,
        );
    }
}
