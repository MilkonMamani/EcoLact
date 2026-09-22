<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solo lectura: consulta el resultado de la propia producción reflejado en
 * producto_stock. El ajuste manual de inventario es exclusivo de VENTAS.
 */
final class ProductoController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $productos = collect($this->obtenerStockGeneral->execute())
            ->filter(fn (StockItem $item): bool => $item->tipo() === 'PRODUCTO');

        if ($request->filled('buscar')) {
            $texto = mb_strtolower($request->string('buscar')->toString());
            $productos = $productos->filter(fn (StockItem $item): bool => str_contains(mb_strtolower($item->nombre()), $texto));
        }

        $todos = $productos->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'productos' => $todos->count(),
                    'stock_total' => round((float) $todos->sum(fn (StockItem $i): float => $i->cantidadActual()), 2),
                    'agotados' => $todos->filter(fn (StockItem $i): bool => $i->sinStock())->count(),
                ],
                'filas' => $todos->map(fn (StockItem $i): array => [
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
        $detalle = $this->obtenerDetalleStock->execute('PRODUCTO', $producto->id);

        abort_if($detalle === null, 404);

        return response()->json([
            'data' => [
                'tipo' => $detalle->tipo,
                'id' => $detalle->id,
                'nombre' => $detalle->nombre,
                'unidad_medida' => $detalle->unidadMedida,
                'cantidad_actual' => $detalle->cantidadActual,
                'estado' => $detalle->estado,
                'movimientos' => $detalle->movimientos,
            ],
        ]);
    }
}
