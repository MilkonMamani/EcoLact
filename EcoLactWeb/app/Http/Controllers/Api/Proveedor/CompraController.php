<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Liquidacion\ObtenerLiquidaciones;
use App\Application\Ventas\ObtenerVentasProveedor;
use App\Domain\Pago\Liquidacion;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Mis compras": cada fila de ventas_producto ya es una compra individual
 * (un producto, una cantidad, un importe). El esquema no tiene un concepto
 * de "pedido" que agrupe varias filas, así que no se inventa esa agrupación.
 */
final class CompraController extends Controller
{
    public function __construct(
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
        private readonly ObtenerLiquidaciones $obtenerLiquidaciones,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VentaProductoModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $compras = $proveedorId !== null ? $this->obtenerVentasProveedor->execute($proveedorId) : [];

        $filtradas = collect($compras)->filter(function (VentaProducto $compra) use ($request): bool {
            if ($request->filled('producto_id') && $compra->productoId() !== $request->integer('producto_id')) {
                return false;
            }

            return true;
        })->sortByDesc(fn (VentaProducto $c) => $c->fecha())->values();

        $idsFiltrados = $filtradas->map(fn (VentaProducto $c): int => $c->productoId())->unique();
        $productosPorId = ProductoModel::whereIn('id', $idsFiltrados)->pluck('nombre', 'id');

        return response()->json([
            'data' => [
                'kpis' => [
                    'compras' => $filtradas->count(),
                    'unidades_adquiridas' => round((float) $filtradas->sum(fn (VentaProducto $c): float => $c->cantidad()), 2),
                    'total_comprado' => round((float) $filtradas->sum(fn (VentaProducto $c): float => $c->importeTotal()), 2),
                ],
                'filas' => $filtradas->map(fn (VentaProducto $c): array => [
                    ...$this->serializar($c),
                    'producto_nombre' => $productosPorId[$c->productoId()] ?? null,
                ])->all(),
            ],
        ]);
    }

    public function show(Request $request, VentaProductoModel $venta): JsonResponse
    {
        Gate::authorize('view', $venta);

        $venta->load('producto');

        $liquidacionRelacionada = null;

        if ($venta->forma_pago === 'DESCUENTO_PLANILLA' && $venta->periodo_id !== null) {
            $proveedorId = $request->user()->proveedor?->id;
            $liquidaciones = $proveedorId !== null ? $this->obtenerLiquidaciones->execute($proveedorId) : [];
            $liquidacionRelacionada = collect($liquidaciones)
                ->first(fn (Liquidacion $l): bool => $l->periodoId() === $venta->periodo_id);
        }

        return response()->json([
            'data' => [
                'id' => $venta->id,
                'producto_id' => $venta->producto_id,
                'producto_nombre' => $venta->producto?->nombre,
                'cantidad' => (float) $venta->cantidad,
                'precio_unitario' => (float) $venta->precio_unitario,
                'importe_total' => (float) $venta->importe_total,
                'forma_pago' => $venta->forma_pago,
                'fecha' => $venta->fecha?->format(DATE_ATOM),
                'liquidacion_relacionada_id' => $liquidacionRelacionada?->id(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializar(VentaProducto $venta): array
    {
        return [
            'id' => $venta->id(),
            'producto_id' => $venta->productoId(),
            'cantidad' => $venta->cantidad(),
            'precio_unitario' => $venta->precioUnitario(),
            'importe_total' => $venta->importeTotal(),
            'forma_pago' => $venta->formaPago(),
            'fecha' => $venta->fecha()->format(DATE_ATOM),
        ];
    }
}
