<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\StockItem;
use App\Application\Ventas\ObtenerTarifasProducto;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Ventas\ProductoPrecio;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solo lectura: consulta el resultado de la propia producción reflejado en
 * producto_stock. El ajuste manual de inventario es exclusivo de VENTAS.
 *
 * El detalle incluye los precios VIGENTES (público, mayorista con su cantidad
 * mínima, proveedor) que configura el ADMINISTRADOR, solo como información:
 * esta API no expone ninguna forma de crearlos ni cambiarlos
 * (ProductoPrecioPolicy). Un precio sin configurar llega como null.
 */
final class ProductoController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
        private readonly ObtenerTarifasProducto $obtenerTarifas,
        private readonly ProductoRepository $productos,
        private readonly RecetaRepository $recetas,
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

        $dominio = $this->productos->buscarPorId($producto->id);
        $tarifas = $this->obtenerTarifas->execute($producto->id);
        $receta = $this->recetas->buscarActivaPorProducto($producto->id);

        return response()->json([
            'data' => [
                'tipo' => $detalle->tipo,
                'id' => $detalle->id,
                'nombre' => $detalle->nombre,
                'nombre_base' => $dominio?->nombreBase(),
                'presentacion' => $dominio?->presentacion(),
                'unidad_medida' => $detalle->unidadMedida,
                'cantidad_actual' => $detalle->cantidadActual,
                'estado' => $detalle->estado,
                'movimientos' => $detalle->movimientos,
                'precios' => [
                    'publico' => $this->precio($tarifas->publico),
                    'mayorista' => $this->precio($tarifas->mayorista),
                    'proveedor' => $this->precio($tarifas->proveedor),
                ],
                'ultima_produccion' => $this->ultimaProduccion($producto->id),
                'receta' => $receta === null ? null : [
                    'id' => $receta->id(),
                    'nombre' => $receta->nombre(),
                    'version' => $receta->version(),
                    'estado' => $receta->estado()->value,
                ],
            ],
        ]);
    }

    /** @return array{precio: float, cantidad_minima: ?float, desde: string}|null */
    private function precio(?ProductoPrecio $precio): ?array
    {
        return $precio === null ? null : [
            'precio' => $precio->precio(),
            'cantidad_minima' => $precio->cantidadMinima(),
            'desde' => $precio->fechaInicio()->format('Y-m-d'),
        ];
    }

    /** @return array{lote_id: int, codigo: string, fecha: ?string, estado: string}|null */
    private function ultimaProduccion(int $productoId): ?array
    {
        $fila = DB::table('produccion_detalles as d')
            ->join('lotes_produccion as l', 'l.id', '=', 'd.lote_produccion_id')
            ->where('d.producto_id', $productoId)
            ->orderByDesc('l.fecha_hora_inicio')
            ->orderByDesc('l.id')
            ->first(['l.id', 'l.codigo', 'l.fecha_hora_inicio', 'l.estado']);

        return $fila === null ? null : [
            'lote_id' => (int) $fila->id,
            'codigo' => $fila->codigo,
            'fecha' => $fila->fecha_hora_inicio !== null ? date(DATE_ATOM, strtotime((string) $fila->fecha_hora_inicio)) : null,
            'estado' => $fila->estado,
        ];
    }
}
