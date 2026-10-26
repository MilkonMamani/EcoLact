<?php

namespace App\Application\Caja;

use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TipoMovimientoCaja;
use Carbon\CarbonImmutable;

/**
 * Filtros del historial de Caja. Por defecto muestra el mes en curso hasta
 * hoy; las tarjetas superiores (saldo y "hoy") NO dependen de estos filtros.
 */
final readonly class CajaFiltro
{
    public function __construct(
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
        public ?string $busqueda,
        public ?TipoMovimientoCaja $tipo,
        public ?CategoriaMovimientoCaja $categoria,
        public int $porPagina = 15,
    ) {}

    public static function resolver(?string $desde, ?string $hasta, ?string $busqueda, ?string $tipo, ?string $categoria): self
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));
        $fecha = static function (?string $valor) use ($hoy): ?CarbonImmutable {
            if ($valor === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
                return null;
            }

            return CarbonImmutable::createFromFormat('!Y-m-d', $valor, $hoy->timezone) ?: null;
        };

        $inicio = ($fecha($desde) ?? $hoy->startOfMonth())->startOfDay();
        $fin = ($fecha($hasta) ?? $hoy)->endOfDay();

        if ($inicio > $fin) {
            [$inicio, $fin] = [$fin->startOfDay(), $inicio->endOfDay()];
        }

        $busqueda = $busqueda !== null ? mb_substr(trim($busqueda), 0, 100) : '';

        return new self(
            desde: $inicio,
            hasta: $fin,
            busqueda: $busqueda !== '' ? $busqueda : null,
            tipo: TipoMovimientoCaja::tryFrom((string) $tipo),
            categoria: CategoriaMovimientoCaja::tryFrom((string) $categoria),
        );
    }
}
