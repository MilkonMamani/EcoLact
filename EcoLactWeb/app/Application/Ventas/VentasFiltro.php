<?php

namespace App\Application\Ventas;

use Carbon\CarbonImmutable;

/**
 * Filtros de la pantalla principal de Ventas. A diferencia de Calidad, aquí
 * el periodo SÍ tiene un valor por defecto (esta semana): a diferencia del
 * análisis de calidad, las ventas son una operación diaria y frecuente.
 */
final readonly class VentasFiltro
{
    public function __construct(
        public string $periodo,
        public CarbonImmutable $fechaDesde,
        public CarbonImmutable $fechaHasta,
        public ?string $busqueda,
        public ?string $tipoCliente,
        public ?string $formaPago,
        public ?string $estado,
        public ?int $vendedorId = null,
    ) {}

    public static function resolver(
        ?string $periodo,
        ?string $desde,
        ?string $hasta,
        ?string $busqueda,
        ?string $tipoCliente,
        ?string $formaPago,
        ?string $estado,
        ?int $vendedorId = null,
    ): self {
        $periodo ??= 'esta_semana';
        $hoy = CarbonImmutable::now(config('app.timezone'));

        [$fechaDesde, $fechaHasta] = match ($periodo) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde, $hoy->timezone)->startOfDay() : $hoy->startOfWeek(),
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta, $hoy->timezone)->endOfDay() : $hoy->endOfWeek(),
            ],
            default => [$hoy->startOfWeek(), $hoy->endOfWeek()],
        };

        return new self(
            periodo: in_array($periodo, ['hoy', 'esta_semana', 'este_mes', 'personalizado'], true) ? $periodo : 'esta_semana',
            vendedorId: $vendedorId,
            fechaDesde: $fechaDesde,
            fechaHasta: $fechaHasta,
            busqueda: $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null,
            tipoCliente: $tipoCliente !== null && $tipoCliente !== '' ? $tipoCliente : null,
            formaPago: $formaPago !== null && $formaPago !== '' ? $formaPago : null,
            estado: $estado !== null && $estado !== '' ? $estado : null,
        );
    }
}
