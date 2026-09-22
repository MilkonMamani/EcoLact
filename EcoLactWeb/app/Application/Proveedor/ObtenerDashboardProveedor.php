<?php

namespace App\Application\Proveedor;

use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisProveedor;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Application\Liquidacion\ObtenerResumenLiquidacionesProveedor;
use App\Application\Ventas\ObtenerVentasProveedor;
use App\Domain\Entrega\Entrega;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ValorizacionEntregaRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Carbon\CarbonImmutable;

/**
 * Compone el Dashboard del proveedor reutilizando los Use Cases ya
 * existentes de cada módulo de autoservicio (Entregas, Calidad,
 * Liquidaciones, Compras, Comunicados), sin duplicar su lógica.
 */
final class ObtenerDashboardProveedor
{
    private const MAX_ENTREGAS_PREVIA = 5;

    private const MAX_COMPRAS_PREVIA = 3;

    private const MAX_COMUNICADOS_PREVIA = 3;

    public function __construct(
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerAnalisisProveedor $obtenerAnalisisProveedor,
        private readonly ObtenerResumenLiquidacionesProveedor $obtenerResumenLiquidacionesProveedor,
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
        private readonly ObtenerComunicados $obtenerComunicados,
        private readonly PeriodoLiquidacionRepository $periodos,
        private readonly LiquidacionRepository $liquidaciones,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ValorizacionEntregaRepository $valorizaciones,
    ) {}

    public function execute(int $userId, int $proveedorId, ?string $periodo, ?string $desde, ?string $hasta): DashboardProveedorData
    {
        $entregas = collect($this->obtenerEntregasProveedor->execute($proveedorId));
        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($periodo, $desde, $hasta);

        $entregasFiltradas = collect($entregas)->filter(
            fn (Entrega $entrega): bool => $fechaDesde === null || $fechaHasta === null
                || ($entrega->fechaHora() >= $fechaDesde && $entrega->fechaHora() <= $fechaHasta)
        )->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        $analisis = collect($this->obtenerAnalisisProveedor->execute($userId))
            ->sortByDesc(fn ($a) => $a->fechaAnalisis())
            ->values();

        $resumenLiquidaciones = $this->obtenerResumenLiquidacionesProveedor->execute($proveedorId);
        $ultimaLiquidacionPeriodo = $resumenLiquidaciones->ultimaLiquidacion !== null
            ? $this->periodos->buscarPorId($resumenLiquidaciones->ultimaLiquidacion->periodoId())
            : null;

        $compras = collect($this->obtenerVentasProveedor->execute($proveedorId))
            ->sortByDesc(fn ($c) => $c->fecha())
            ->values();

        $comunicados = $this->obtenerComunicados->execute($userId);
        usort($comunicados, static fn ($a, $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        $resultadosPorEntrega = $analisis->mapWithKeys(fn ($a) => [$a->entregaId() => $a->resultado()?->value])->all();
        $entregaIds = $entregas->map(fn (Entrega $entrega): ?int => $entrega->id())->filter()->all();
        $valorizaciones = collect($this->valorizaciones->listarPorEntregaIds($entregaIds))->keyBy(fn ($v) => $v->entregaId());
        $valorizacionIds = $valorizaciones->map(fn ($valorizacion): ?int => $valorizacion->id())->filter()->all();
        $valorizacionesIncluidas = collect($this->liquidaciones->listarValorizacionIdsIncluidas($valorizacionIds))->flip();
        $estimadoValorizadoSinLiquidar = $valorizaciones->sum(function ($valorizacion) use ($valorizacionesIncluidas): float {
            return $valorizacionesIncluidas->has($valorizacion->id()) ? 0.0 : $valorizacion->importeTotal();
        });
        $estimadoSinValorizar = $entregas->sum(function (Entrega $entrega) use ($resultadosPorEntrega, $valorizaciones): float {
            if ($entrega->id() !== null && $valorizaciones->has($entrega->id())) {
                return 0.0;
            }

            if (($resultadosPorEntrega[$entrega->id()] ?? null) === ResultadoCalidad::RECHAZADA->value) {
                return 0.0;
            }

            $precio = $this->preciosLeche->buscarVigente($entrega->fechaHora());

            return $precio === null ? 0.0 : $entrega->litros() * $precio->precioLitro();
        });

        return new DashboardProveedorData(
            kpis: [
                'entregas' => $entregasFiltradas->count(),
                // La leche de una muestra RECHAZADA nunca se acopió: no
                // debe sumar como litros entregados, aunque el registro se
                // conserve para el historial de calidad.
                'litros' => round((float) $entregasFiltradas->sum(
                    fn (Entrega $e): float => ($resultadosPorEntrega[$e->id()] ?? null) === 'RECHAZADA' ? 0.0 : $e->litros()
                ), 2),
            ],
            ultimoAnalisis: $analisis->first(),
            ultimaLiquidacion: $resumenLiquidaciones->ultimaLiquidacion,
            ultimaLiquidacionPeriodo: $ultimaLiquidacionPeriodo,
            porCobrar: round($resumenLiquidaciones->porCobrar + $estimadoValorizadoSinLiquidar + $estimadoSinValorizar, 2),
            ultimasEntregas: $entregasFiltradas->take(self::MAX_ENTREGAS_PREVIA)->all(),
            resultadosPorEntrega: $resultadosPorEntrega,
            comprasRecientes: $compras->take(self::MAX_COMPRAS_PREVIA)->all(),
            comunicadosRecientes: array_slice($comunicados, 0, self::MAX_COMUNICADOS_PREVIA),
        );
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function rangoPeriodo(?string $periodo, ?string $desde, ?string $hasta): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        return match ($periodo) {
            'esta_semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'semana_anterior' => [$hoy->subWeek()->startOfWeek(), $hoy->subWeek()->endOfWeek()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde)->startOfDay() : null,
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta)->endOfDay() : null,
            ],
            default => [null, null],
        };
    }
}
