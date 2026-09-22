<?php

namespace App\Application\Calidad;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Filtros de la pantalla principal de Calidad. A diferencia de
 * DashboardPeriodo, el rango de fecha es OPCIONAL: el análisis de calidad no
 * ocurre a diario (el analista solo muestrea algunos proveedores), así que
 * el listado por defecto no debe acotarse a "hoy" ni a ningún periodo.
 */
final readonly class AnalisisCalidadFiltro
{
    public function __construct(
        public ?string $busqueda,
        public ?int $zonaId,
        public ?string $resultado,
        public ?string $estado,
        public ?CarbonImmutable $fechaDesde,
        public ?CarbonImmutable $fechaHasta,
    ) {}

    public static function resolver(
        ?string $busqueda,
        ?int $zonaId,
        ?string $resultado,
        ?string $estado,
        ?string $fecha,
        ?string $desde,
        ?string $hasta,
    ): self {
        $hoy = CarbonImmutable::now(config('app.timezone'));
        $ayer = $hoy->subDay();
        $semanaAnterior = $hoy->subWeek();

        [$fechaDesde, $fechaHasta] = match ($fecha) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'ayer' => [$ayer->startOfDay(), $ayer->endOfDay()],
            'esta_semana' => [$hoy->startOfWeek(CarbonInterface::MONDAY), $hoy->endOfWeek(CarbonInterface::SUNDAY)],
            'semana_anterior' => [$semanaAnterior->startOfWeek(CarbonInterface::MONDAY), $semanaAnterior->endOfWeek(CarbonInterface::SUNDAY)],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde, $hoy->timezone)->startOfDay() : null,
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta, $hoy->timezone)->endOfDay() : null,
            ],
            default => [null, null],
        };

        return new self(
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            zonaId: $zonaId,
            resultado: $resultado !== null && $resultado !== '' ? $resultado : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
            fechaDesde: $fechaDesde,
            fechaHasta: $fechaHasta,
        );
    }
}
