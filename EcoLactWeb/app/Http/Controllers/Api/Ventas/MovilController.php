<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Ventas\ConsultarSaldoDescuentoProveedor;
use App\Application\Ventas\ObtenerCatalogoVenta;
use App\Application\Ventas\ObtenerProductosActivos;
use App\Application\Ventas\ObtenerStockProducto;
use App\Application\Ventas\ObtenerTarifasProducto;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class MovilController extends Controller
{
    public function catalogo(ObtenerCatalogoVenta $catalogo, ObtenerProveedores $proveedores, ObtenerTarifasProducto $tarifas, ObtenerProductosActivos $productos, ObtenerStockProducto $stock): JsonResponse
    {
        Gate::authorize('viewGeneral', VentaProductoModel::class);
        $lista = $catalogo->execute();
        $ids = array_column($lista, 'id');
        foreach ($productos->execute() as $producto) {
            if (! in_array($producto->id(), $ids, true)) {
                $lista[] = ['id' => $producto->id(), 'nombre' => $producto->etiqueta(), 'stock' => $stock->execute($producto->id())?->cantidadActual() ?? 0.0,
                    'publico' => null, 'mayorista' => null, 'minimoMayorista' => null, 'proveedor' => null];
            }
        }

        return response()->json(['data' => [
            'productos' => array_map(function (array $p) use ($tarifas): array {
                $t = $tarifas->execute($p['id']);
                $limite = $t->minimoMayorista();
                $rangos = [['PUBLICO', 0.0, $limite], ['PROVEEDOR', 0.0, null]];
                if ($limite !== null) {
                    $rangos[] = ['PUBLICO', $limite, null];
                }
                $p['rangos'] = [];
                foreach ($rangos as [$cliente, $desde, $hasta]) {
                    $precio = $t->aplicable($cliente === 'PROVEEDOR', $desde);
                    if ($precio !== null) {
                        $p['rangos'][] = ['cliente' => $cliente, 'desde' => $desde, 'hasta' => $hasta, 'precio' => $precio->precio(), 'tipo' => $precio->tipoCliente()];
                    }
                }

                return $p;
            }, $lista),
            'proveedores' => array_values(array_map(
                fn ($p): array => ['id' => $p->id(), 'nombre' => $p->nombreCompleto(), 'documento' => $p->dni()],
                array_filter($proveedores->execute(), fn ($p): bool => $p->estado() === EstadoUsuario::ACTIVO),
            )),
        ]]);
    }

    /** The same domain price resolver used by RegistrarVentaProducto, without a sale or stock mutation. */
    public function cotizar(StoreVentaProductoRequest $request, ObtenerTarifasProducto $tarifas, ConsultarSaldoDescuentoProveedor $saldo): JsonResponse
    {
        Gate::authorize('create', VentaProductoModel::class);
        $datos = $request->venta();
        $precio = $tarifas->execute($datos['producto_id'])->aplicable($datos['proveedor_id'] !== null, $datos['cantidad']);
        abort_if($precio === null, 422, 'No existe un precio vigente para esta cantidad y cliente.');
        $total = round($precio->precio() * $datos['cantidad'], 2);

        if ($datos['forma_pago'] === 'DESCUENTO_PLANILLA') {
            abort_if($datos['proveedor_id'] === null, 422, 'El descuento semanal solo corresponde a proveedores.');
            abort_if($total > $saldo->execute($datos['proveedor_id']), 422, 'El monto supera el saldo disponible del proveedor.');
        }

        return response()->json(['data' => [
            'precio_unitario' => $precio->precio(),
            'tipo_cliente' => $precio->tipoCliente(),
            'importe_total' => $total,
        ]]);
    }

    /** Own sales only, including the complete commercial snapshot needed for offline receipts. */
    public function index(Request $request, ProductoRepository $productos): JsonResponse
    {
        Gate::authorize('viewGeneral', VentaProductoModel::class);
        $etiquetas = collect($productos->listar())->mapWithKeys(fn ($p): array => [$p->id() => $p->etiqueta()]);
        $ventas = VentaProductoModel::with(['producto', 'proveedor', 'comprador', 'registradoPor'])
            ->where('registrado_por_user_id', $request->user()->id)
            ->with('registradoPor.personalVentas')->orderByDesc('fecha')->orderByDesc('id')->get();

        return response()->json(['data' => $ventas->map(fn (VentaProductoModel $v): array => [
            'id' => $v->id,
            'codigo' => VentaProducto::codigo($v->id),
            'producto_id' => $v->producto_id,
            'producto_nombre' => $etiquetas[$v->producto_id] ?? $v->producto?->nombre ?? '',
            'cliente' => $v->proveedor !== null ? trim($v->proveedor->nombres.' '.$v->proveedor->apellidos) : ($v->comprador?->nombre ?? 'Público general'),
            'documento' => $v->proveedor?->dni ?? $v->comprador?->documento,
            'proveedor_id' => $v->proveedor_id,
            'comprador_id' => $v->comprador_id,
            'tipo_cliente' => $v->tipo_cliente,
            'cantidad' => (float) $v->cantidad,
            'precio_unitario' => (float) $v->precio_unitario,
            'importe_total' => (float) $v->importe_total,
            'forma_pago' => $v->forma_pago,
            'fecha' => $v->fecha->format(DATE_ATOM),
            'vendedor' => $v->registradoPor?->personalVentas !== null
                ? trim($v->registradoPor->personalVentas->nombres.' '.$v->registradoPor->personalVentas->apellidos)
                : ($v->registradoPor?->username ?? ''),
            'estado' => $v->estado,
        ])->all()]);
    }
}
