<?php

namespace App\Application\PersonalVentas;

use App\Application\Comunicacion\ObtenerNotificaciones;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Application\Ventas\ObtenerVentas;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Ventas\VentaProducto;
use Carbon\CarbonImmutable;

/**
 * Compone el Dashboard de VENTAS reutilizando los Use Cases ya existentes
 * de Ventas y Stock, sin duplicar su lógica. "Esta semana" es el periodo
 * inicial: mismo criterio que la pantalla de Ventas (VentasFiltro).
 */
final class ObtenerDashboardVentas
{
    private const MAX_VENTAS_RECIENTES = 5;

    private const MAX_STOCK_CRITICO = 5;

    private const MAX_MAS_VENDIDOS = 5;

    private const MAX_NOTIFICACIONES_PREVIA = 5;

    public function __construct(
        private readonly ObtenerVentas $obtenerVentas,
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerNotificaciones $obtenerNotificaciones,
        private readonly ProductoRepository $productos,
    ) {}

    public function execute(int $userId, ?string $periodo, ?string $desde, ?string $hasta): DashboardVentasData
    {
        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($periodo, $desde, $hasta);

        $ventasDelPeriodo = collect($this->obtenerVentas->execute())
            ->filter(fn (VentaProducto $v): bool => $v->fecha() >= $fechaDesde && $v->fecha() <= $fechaHasta)
            ->sortByDesc(fn (VentaProducto $v) => [$v->fecha(), $v->id()])
            ->values();

        $totalVendido = round((float) $ventasDelPeriodo->sum(fn (VentaProducto $v): float => $v->importeTotal()), 2);
        $cantidadVentas = $ventasDelPeriodo->count();

        $nombresPorId = collect($this->productos->listar())->mapWithKeys(fn ($p) => [$p->id() => $p->nombre()]);
        $unidadesPorId = collect($this->productos->listar())->mapWithKeys(fn ($p) => [$p->id() => $p->unidadMedida()]);

        $masVendidos = $ventasDelPeriodo->groupBy(fn (VentaProducto $v) => $v->productoId())
            ->map(fn ($grupo, $productoId): array => [
                'producto' => $nombresPorId[$productoId] ?? ('Producto #'.$productoId),
                'cantidad' => round((float) $grupo->sum(fn (VentaProducto $v): float => $v->cantidad()), 2),
            ])
            ->sortByDesc('cantidad')
            ->values()
            ->take(self::MAX_MAS_VENDIDOS)
            ->all();

        $stock = collect($this->obtenerStockGeneral->execute())->filter(fn (StockItem $i): bool => $i->tipo() === 'PRODUCTO');
        $stockCritico = $stock->filter(fn (StockItem $i): bool => $i->sinStock())
            ->take(self::MAX_STOCK_CRITICO)
            ->map(fn (StockItem $i): array => ['producto' => $i->nombre(), 'stock' => $i->cantidadActual(), 'unidad' => $i->unidadMedida()])
            ->values()
            ->all();

        $notificaciones = $this->obtenerNotificaciones->execute($userId);

        return new DashboardVentasData(
            kpis: [
                'ventas' => $cantidadVentas,
                'totalVendido' => $totalVendido,
                'productosVendidos' => round((float) $ventasDelPeriodo->sum(fn (VentaProducto $v): float => $v->cantidad()), 2),
                'ticketPromedio' => $cantidadVentas > 0 ? round($totalVendido / $cantidadVentas, 2) : null,
            ],
            ventasRecientes: $ventasDelPeriodo->take(self::MAX_VENTAS_RECIENTES)->all(),
            stockCritico: $stockCritico,
            masVendidos: $masVendidos,
            notificacionesRecientes: array_slice($notificaciones, 0, self::MAX_NOTIFICACIONES_PREVIA),
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rangoPeriodo(?string $periodo, ?string $desde, ?string $hasta): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));
        $periodo ??= 'esta_semana';

        return match ($periodo) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'ayer' => [$hoy->subDay()->startOfDay(), $hoy->subDay()->endOfDay()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde)->startOfDay() : $hoy->startOfWeek(),
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta)->endOfDay() : $hoy->endOfWeek(),
            ],
            default => [$hoy->startOfWeek(), $hoy->endOfWeek()],
        };
    }
}
