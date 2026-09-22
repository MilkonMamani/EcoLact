<?php

namespace App\Application\Planta;

use App\Application\Comunicacion\ObtenerNotificaciones;
use App\Application\Produccion\ObtenerInsumos;
use App\Application\Produccion\ObtenerLotesProduccion;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionInsumoRepository;
use App\Domain\Produccion\ProduccionDetalleRepository;
use Carbon\CarbonImmutable;

/**
 * Compone el Dashboard del encargado de planta reutilizando los Use Cases
 * ya existentes de Producción, Insumos y Stock, sin duplicar su lógica.
 * "Hoy" es el periodo inicial: la actividad de planta es operativa y diaria
 * (igual criterio que el Dashboard del acopiador).
 */
final class ObtenerDashboardPlanta
{
    private const MAX_PRODUCCION_RECIENTE = 5;

    private const MAX_PRODUCTOS_DISPONIBLES = 5;

    private const MAX_NOTIFICACIONES_PREVIA = 5;

    public function __construct(
        private readonly ObtenerLotesProduccion $obtenerLotesProduccion,
        private readonly ObtenerInsumos $obtenerInsumos,
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerNotificaciones $obtenerNotificaciones,
        private readonly ProduccionDetalleRepository $produccionDetalles,
        private readonly LoteProduccionInsumoRepository $consumos,
        private readonly InsumoRepository $insumoCatalogo,
    ) {}

    public function execute(int $userId, ?string $periodo, ?string $desde, ?string $hasta): DashboardPlantaData
    {
        $lotes = $this->obtenerLotesProduccion->execute();
        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($periodo, $desde, $hasta);

        $enCurso = array_values(array_filter($lotes, fn (LoteProduccion $l): bool => $l->estado() === 'EN_PROCESO'));

        $delPeriodo = array_values(array_filter(
            $lotes,
            fn (LoteProduccion $l): bool => $fechaDesde === null || $fechaHasta === null
                || ($l->fechaHoraInicio() >= $fechaDesde && $l->fechaHoraInicio() <= $fechaHasta),
        ));

        $finalizadosDelPeriodo = array_values(array_filter($delPeriodo, fn (LoteProduccion $l): bool => $l->estado() === 'FINALIZADO'));

        usort($delPeriodo, static fn (LoteProduccion $a, LoteProduccion $b): int => [$b->fechaHoraInicio(), $b->id()] <=> [$a->fechaHoraInicio(), $a->id()]);

        $insumos = $this->obtenerInsumos->execute();
        $insumosBajoMinimo = array_values(array_filter($insumos, fn ($i) => $i->estaBajoMinimo()));

        $stock = $this->obtenerStockGeneral->execute();
        $productosStock = array_values(array_filter($stock, fn (StockItem $i): bool => $i->tipo() === 'PRODUCTO'));

        $notificaciones = $this->obtenerNotificaciones->execute($userId);

        $productosProducidos = 0.0;
        foreach ($delPeriodo as $l) {
            foreach ($this->produccionDetalles->listarPorLote($l->id()) as $detalle) {
                $productosProducidos += $detalle->cantidadObtenida() ?? 0.0;
            }
        }

        $lecheId = $this->insumoCatalogo->buscarPorCodigo('LECHE')?->id();
        $litrosPorLote = $lecheId === null ? [] : $this->consumos->cantidadUsadaPorLote(
            $lecheId,
            array_values(array_map(fn (LoteProduccion $l): int => $l->id(), array_merge($delPeriodo, $enCurso))),
        );

        return new DashboardPlantaData(
            kpis: [
                'enCurso' => count($enCurso),
                'lotesProducidos' => count($finalizadosDelPeriodo),
                'litrosUtilizados' => round(array_sum(array_map(fn (LoteProduccion $l): float => $litrosPorLote[$l->id()] ?? 0.0, $delPeriodo)), 2),
                'productosProducidos' => round($productosProducidos, 2),
            ],
            produccionActual: array_slice($enCurso, 0, self::MAX_PRODUCCION_RECIENTE),
            produccionReciente: array_slice($delPeriodo, 0, self::MAX_PRODUCCION_RECIENTE),
            insumosBajoMinimo: $insumosBajoMinimo,
            inventario: [
                'total' => count($insumos),
                'suficiente' => count($insumos) - count($insumosBajoMinimo),
                'bajo' => count(array_filter($insumosBajoMinimo, fn ($i) => $i->stockActual() > 0.0)),
                'sinStock' => count(array_filter($insumos, fn ($i) => $i->stockActual() <= 0.0)),
            ],
            productosDisponibles: array_map(
                fn (StockItem $i): array => ['producto' => $i->nombre(), 'stock' => $i->cantidadActual(), 'unidad' => $i->unidadMedida()],
                array_slice($productosStock, 0, self::MAX_PRODUCTOS_DISPONIBLES),
            ),
            notificacionesRecientes: array_slice($notificaciones, 0, self::MAX_NOTIFICACIONES_PREVIA),
            litrosPorLote: $litrosPorLote,
        );
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function rangoPeriodo(?string $periodo, ?string $desde, ?string $hasta): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        return match ($periodo) {
            'ayer' => [$hoy->subDay()->startOfDay(), $hoy->subDay()->endOfDay()],
            'esta_semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde)->startOfDay() : null,
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta)->endOfDay() : null,
            ],
            // 'hoy' y el valor por defecto: la actividad de planta es diaria.
            default => [$hoy->startOfDay(), $hoy->endOfDay()],
        };
    }
}
