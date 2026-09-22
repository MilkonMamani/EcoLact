<?php

namespace App\Http\Controllers\Web\PersonalVentas;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Application\Ventas\ObtenerMovimientosProductos;
use App\Domain\Produccion\ProductoMovimiento;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del VENTAS: "Productos" (Stock de productos + Movimientos). VENTAS
 * consulta el stock que entra automáticamente al finalizar cada producción y
 * descuenta lo vendido (ya reflejado automáticamente por
 * RegistrarVentaProducto), pero nunca registra producción ni insumos.
 */
final class ProductoController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
        private readonly ObtenerMovimientosProductos $obtenerMovimientosProductos,
    ) {}

    public function index(Request $request): View
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

        $kpis = [
            'productos' => $todos->count(),
            'stockTotal' => round((float) $todos->sum(fn (StockItem $i): float => $i->cantidadActual()), 2),
            'agotados' => $todos->filter(fn (StockItem $i): bool => $i->sinStock())->count(),
        ];

        return view('ventas-panel.productos.index', [
            'productos' => $productos->values()->all(),
            'kpis' => $kpis,
        ]);
    }

    public function show(ProductoModel $producto): View
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        $detalle = $this->obtenerDetalleStock->execute('PRODUCTO', $producto->id);

        abort_if($detalle === null, 404);

        return view('ventas-panel.productos.show', ['detalle' => $detalle, 'producto' => $producto]);
    }

    public function movimientos(Request $request): View
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

        $kpis = [
            'total' => $filtrados->count(),
            'entradas' => $filtrados->filter(fn (ProductoMovimiento $m): bool => $m->tipo() === 'INGRESO_PRODUCCION')->count(),
            'salidas' => $filtrados->filter(fn (ProductoMovimiento $m): bool => $m->tipo() === 'VENTA')->count(),
        ];

        return view('ventas-panel.productos.movimientos', [
            'movimientos' => $filtrados->all(),
            'productosPorId' => $productos,
            'productosDisponibles' => $productos->sort(),
            'kpis' => $kpis,
        ]);
    }
}
