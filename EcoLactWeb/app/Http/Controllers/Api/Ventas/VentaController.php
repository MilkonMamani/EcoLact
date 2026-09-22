<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Ventas\Contracts\VentasListadoQuery;
use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\ObtenerProductosActivos;
use App\Application\Ventas\ObtenerStockProducto;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Application\Ventas\VentasFiltro;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VentaController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly RegistrarVentaProducto $registrarVentaProducto,
        private readonly VentasListadoQuery $ventasListadoQuery,
        private readonly ObtenerProductosActivos $obtenerProductosActivos,
        private readonly ObtenerCompradores $obtenerCompradores,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerStockProducto $obtenerStockProducto,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewGeneral', VentaProductoModel::class);

        $filtro = VentasFiltro::resolver(
            periodo: $request->string('periodo')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            busqueda: $request->string('q')->toString() ?: null,
            tipoCliente: $request->string('tipo_cliente')->toString() ?: null,
            formaPago: $request->string('forma_pago')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        $datos = $this->ventasListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => ['kpis' => $datos->kpis, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    /**
     * Datos de apoyo para armar el wizard de "Nueva venta": productos
     * activos con su stock actual, compradores registrados y proveedores
     * (un proveedor también puede ser comprador, pagando vía
     * DESCUENTO_PLANILLA con cargo a su próxima liquidación).
     */
    public function crear(): JsonResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        $productos = $this->obtenerProductosActivos->execute();
        $stockPorProducto = collect($productos)->mapWithKeys(
            fn ($p) => [$p->id() => $this->obtenerStockProducto->execute($p->id())?->cantidadActual() ?? 0.0]
        );

        return response()->json([
            'data' => [
                'productos' => array_map(
                    fn ($p): array => ['id' => $p->id(), 'nombre' => $p->nombre(), 'unidad_medida' => $p->unidadMedida(), 'stock' => $stockPorProducto[$p->id()] ?? 0.0],
                    $productos,
                ),
                'compradores' => array_map(
                    fn ($c): array => ['id' => $c->id(), 'nombre' => $c->nombre(), 'tipo_cliente' => $c->tipoCliente()],
                    $this->obtenerCompradores->execute(),
                ),
                'proveedores' => array_map(
                    fn ($p): array => ['id' => $p->id(), 'nombre_completo' => $p->nombreCompleto(), 'dni' => $p->dni()],
                    $this->obtenerProveedores->execute(),
                ),
            ],
        ]);
    }

    public function show(VentaProductoModel $venta): JsonResponse
    {
        Gate::authorize('view', $venta);

        $venta->load(['producto', 'proveedor', 'comprador']);

        return response()->json([
            'data' => [
                'id' => $venta->id,
                'producto_id' => $venta->producto_id,
                'producto_nombre' => $venta->producto?->nombre,
                'proveedor_id' => $venta->proveedor_id,
                'comprador_id' => $venta->comprador_id,
                'comprador_nombre' => $venta->comprador->nombre ?? ($venta->proveedor === null ? null : trim($venta->proveedor->nombres.' '.$venta->proveedor->apellidos)),
                'tipo_cliente' => $venta->tipo_cliente,
                'cantidad' => (float) $venta->cantidad,
                'precio_unitario' => (float) $venta->precio_unitario,
                'importe_total' => (float) $venta->importe_total,
                'forma_pago' => $venta->forma_pago,
                'estado' => $venta->estado,
                'fecha' => $venta->fecha?->format(DATE_ATOM),
            ],
        ]);
    }

    /**
     * Registra la venta de UN producto (el esquema no agrupa varias filas en
     * un "ticket"): para un carrito de varios productos, el cliente móvil
     * debe llamar este endpoint una vez por producto.
     */
    public function store(StoreVentaProductoRequest $request): JsonResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        $datos = $request->validated();

        $venta = $this->registrarVentaProducto->execute(
            productoId: (int) $datos['producto_id'],
            tipoCliente: $datos['tipo_cliente'],
            cantidad: (float) $datos['cantidad'],
            formaPago: $datos['forma_pago'],
            registradoPorUserId: $request->user()->id,
            proveedorId: isset($datos['proveedor_id']) ? (int) $datos['proveedor_id'] : null,
            compradorId: isset($datos['comprador_id']) ? (int) $datos['comprador_id'] : null,
        );

        return response()->json([
            'data' => [
                'id' => $venta->id(),
                'producto_id' => $venta->productoId(),
                'cantidad' => $venta->cantidad(),
                'precio_unitario' => $venta->precioUnitario(),
                'importe_total' => $venta->importeTotal(),
                'forma_pago' => $venta->formaPago(),
                'estado' => $venta->estado(),
                'fecha' => $venta->fecha()->format(DATE_ATOM),
            ],
        ], 201);
    }
}
