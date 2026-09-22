<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Punto de consulta unificado de inventario (productos terminados +
 * insumos), solo lectura para ADMINISTRADOR: ajustar stock sigue siendo
 * exclusivo de ENCARGADO_PLANTA/VENTAS según el elemento.
 */
final class StockController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        return response()->json([
            'data' => array_map(
                static fn (StockItem $item): array => [
                    'tipo' => $item->tipo(),
                    'id' => $item->id(),
                    'nombre' => $item->nombre(),
                    'unidad_medida' => $item->unidadMedida(),
                    'cantidad_actual' => $item->cantidadActual(),
                    'stock_minimo' => $item->stockMinimo(),
                    'estado' => $item->estado(),
                ],
                $this->obtenerStockGeneral->execute(),
            ),
        ]);
    }

    public function show(string $tipo, int $id): JsonResponse
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        $detalle = $this->obtenerDetalleStock->execute(strtoupper($tipo), $id);

        abort_if($detalle === null, 404);

        return response()->json([
            'data' => [
                'tipo' => $detalle->tipo,
                'id' => $detalle->id,
                'nombre' => $detalle->nombre,
                'unidad_medida' => $detalle->unidadMedida,
                'cantidad_actual' => $detalle->cantidadActual,
                'stock_minimo' => $detalle->stockMinimo,
                'estado' => $detalle->estado,
                'movimientos' => $detalle->movimientos,
            ],
        ]);
    }
}
