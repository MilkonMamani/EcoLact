<?php

namespace App\Http\Controllers\Web\Planta;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal del ENCARGADO_PLANTA: "Productos" (Stock de productos). SOLO
 * LECTURA: consulta el resultado de su propia producción reflejado en
 * producto_stock; el ajuste manual de inventario sigue siendo exclusivo de
 * VENTAS. No pasa por ProductoStockPolicy (esa Policy gobierna la pantalla
 * UNIFICADA de admin/ventas, exclusiva de ADMINISTRADOR/VENTAS): esta ruta
 * ya está acotada por el middleware role:ENCARGADO_PLANTA del propio grupo
 * /planta, y producto_stock no tiene datos sensibles por-usuario que
 * proteger con una Policy adicional.
 */
final class ProductoController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
    ) {}

    public function index(Request $request): View
    {
        $productos = collect($this->obtenerStockGeneral->execute())
            ->filter(fn (StockItem $item): bool => $item->tipo() === 'PRODUCTO');

        if ($request->filled('buscar')) {
            $texto = mb_strtolower($request->string('buscar')->toString());
            $productos = $productos->filter(fn (StockItem $item): bool => str_contains(mb_strtolower($item->nombre()), $texto));
        }

        if ($request->filled('estado_stock')) {
            $productos = $productos->filter(function (StockItem $item) use ($request): bool {
                $estadoStock = match (true) {
                    $item->sinStock() => 'agotado',
                    default => 'disponible',
                };

                return $estadoStock === $request->string('estado_stock')->toString();
            });
        }

        $productos = $productos->values();

        $todos = collect($this->obtenerStockGeneral->execute())->filter(fn (StockItem $i): bool => $i->tipo() === 'PRODUCTO');

        $kpis = [
            'productos' => $todos->count(),
            'stockTotal' => round((float) $todos->sum(fn (StockItem $i): float => $i->cantidadActual()), 2),
            'agotados' => $todos->filter(fn (StockItem $i): bool => $i->sinStock())->count(),
        ];

        return view('planta.productos.index', [
            'productos' => $productos->all(),
            'kpis' => $kpis,
        ]);
    }

    public function show(ProductoModel $producto): View
    {
        $detalle = $this->obtenerDetalleStock->execute('PRODUCTO', $producto->id);

        abort_if($detalle === null, 404);

        return view('planta.productos.show', ['detalle' => $detalle]);
    }
}
