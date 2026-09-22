<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Liquidacion\ObtenerLiquidaciones;
use App\Application\Ventas\ObtenerVentasProveedor;
use App\Domain\Pago\Liquidacion;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del PROVEEDOR: "Mis compras". Cada fila de ventas_producto
 * ya es una compra individual (un producto, una cantidad, un importe): el
 * esquema no tiene un concepto de "pedido" que agrupe varias filas, así
 * que no se inventa esa agrupación (a diferencia del wireframe original,
 * que mostraba "3 productos" por compra).
 */
final class CompraController extends Controller
{
    public function __construct(
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
        private readonly ObtenerLiquidaciones $obtenerLiquidaciones,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', VentaProductoModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $compras = $proveedorId !== null ? $this->obtenerVentasProveedor->execute($proveedorId) : [];

        $hoy = CarbonImmutable::now(config('app.timezone'));
        $desde = match ($request->string('periodo')->toString() ?: null) {
            'este_mes' => $hoy->startOfMonth(),
            'ultimos_30_dias' => $hoy->subDays(30),
            default => null,
        };

        $filtradas = collect($compras)->filter(function (VentaProducto $compra) use ($request, $desde): bool {
            if ($desde !== null && $compra->fecha() < $desde) {
                return false;
            }

            if ($request->filled('producto_id') && $compra->productoId() !== $request->integer('producto_id')) {
                return false;
            }

            return true;
        })->sortByDesc(fn (VentaProducto $c) => $c->fecha())->values();

        // Nota: VentaProducto expone productoId() como método, no como
        // propiedad pública, así que pluck('productoId') no puede leerlo
        // (data_get() solo funciona con propiedades públicas o arrays) y
        // devolvería silenciosamente una lista vacía. Se usa map() en su
        // lugar (mismo problema ya detectado en el módulo Stock).
        $idsFiltrados = $filtradas->map(fn (VentaProducto $c): int => $c->productoId())->unique();
        $productosPorId = ProductoModel::whereIn('id', $idsFiltrados)->pluck('nombre', 'id');

        $kpis = [
            'compras' => $filtradas->count(),
            'unidadesAdquiridas' => round((float) $filtradas->sum(fn (VentaProducto $c): float => $c->cantidad()), 2),
            'totalComprado' => round((float) $filtradas->sum(fn (VentaProducto $c): float => $c->importeTotal()), 2),
            'ultimaCompra' => $filtradas->first(),
        ];

        $idsDisponibles = collect($compras)->map(fn (VentaProducto $c): int => $c->productoId())->unique();
        $productosDisponibles = ProductoModel::whereIn('id', $idsDisponibles)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');

        return view('proveedor.compras.index', [
            'compras' => $filtradas->all(),
            'kpis' => $kpis,
            'productosPorId' => $productosPorId,
            'productosDisponibles' => $productosDisponibles,
        ]);
    }

    public function show(Request $request, VentaProductoModel $venta): View
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

        return view('proveedor.compras.show', [
            'venta' => $venta,
            'liquidacionRelacionada' => $liquidacionRelacionada,
        ]);
    }
}
