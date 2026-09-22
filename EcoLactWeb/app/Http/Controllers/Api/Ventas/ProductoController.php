<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Application\Ventas\ObtenerMovimientosProductos;
use App\Domain\Produccion\ProductoMovimiento;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProductoController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
        private readonly ObtenerMovimientosProductos $obtenerMovimientosProductos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        $productos = collect($this->obtenerStockGeneral->execute())
            ->filter(fn (StockItem $item): bool => $item->tipo() === 'PRODUCTO');

        $todos = $productos->values();

        if ($request->filled('buscar')) {
            $texto = mb_strtolower($request->string('buscar')->toString());
            $productos = $productos->filter(fn (StockItem $item): bool => str_contains(mb_strtolower($item->nombre()), $texto));
        }

        if ($request->filled('estado_stock')) {
            $productos = $productos->filter(fn (StockItem $item): bool => ($item->sinStock() ? 'agotado' : 'disponible') === $request->string('estado_stock')->toString());
        }

        return response()->json([
            'data' => [
                'kpis' => [
                    'productos' => $todos->count(),
                    'stock_total' => round((float) $todos->sum(fn (StockItem $i): float => $i->cantidadActual()), 2),
                    'agotados' => $todos->filter(fn (StockItem $i): bool => $i->sinStock())->count(),
                ],
                'filas' => $productos->values()->map(fn (StockItem $i): array => [
                    'id' => $i->id(),
                    'nombre' => $i->nombre(),
                    'unidad_medida' => $i->unidadMedida(),
                    'cantidad_actual' => $i->cantidadActual(),
                    'sin_stock' => $i->sinStock(),
                ])->all(),
            ],
        ]);
    }

    public function show(ProductoModel $producto): JsonResponse
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        $detalle = $this->obtenerDetalleStock->execute('PRODUCTO', $producto->id);

        abort_if($detalle === null, 404);

        return response()->json([
            'data' => [
                'id' => $detalle->id,
                'nombre' => $detalle->nombre,
                'unidad_medida' => $detalle->unidadMedida,
                'cantidad_actual' => $detalle->cantidadActual,
                'estado' => $detalle->estado,
                'movimientos' => $detalle->movimientos,
            ],
        ]);
    }

    public function movimientos(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        $movimientos = collect($this->obtenerMovimientosProductos->execute());

        $productos = collect($this->obtenerStockGeneral->execute())
            ->filter(fn (StockItem $i): bool => $i->tipo() === 'PRODUCTO')
            ->mapWithKeys(fn (StockItem $i) => [$i->id() => $i->nombre()]);

        $filtrados = $movimientos->filter(function (ProductoMovimiento $movimiento) use ($request): bool {
            if ($request->filled('producto_id') && $movimiento->productoId() !== $request->integer('producto_id')) {
                return false;
            }

            if ($request->filled('tipo') && $movimiento->tipo() !== $request->string('tipo')->toString()) {
                return false;
            }

            if ($request->filled('desde') && $movimiento->fecha()->format('Y-m-d') < $request->string('desde')->toString()) {
                return false;
            }

            if ($request->filled('hasta') && $movimiento->fecha()->format('Y-m-d') > $request->string('hasta')->toString()) {
                return false;
            }

            return true;
        })->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'total' => $filtrados->count(),
                    'entradas' => $filtrados->filter(fn (ProductoMovimiento $m): bool => $m->tipo() === 'INGRESO_PRODUCCION')->count(),
                    'salidas' => $filtrados->filter(fn (ProductoMovimiento $m): bool => $m->tipo() === 'VENTA')->count(),
                ],
                'filas' => $filtrados->map(fn (ProductoMovimiento $m): array => [
                    'id' => $m->id(),
                    'producto_id' => $m->productoId(),
                    'producto_nombre' => $productos[$m->productoId()] ?? null,
                    'tipo' => $m->tipo(),
                    'cantidad' => $m->cantidad(),
                    'fecha' => $m->fecha()->format(DATE_ATOM),
                    'observacion' => $m->observacion(),
                ])->all(),
            ],
        ]);
    }
}
