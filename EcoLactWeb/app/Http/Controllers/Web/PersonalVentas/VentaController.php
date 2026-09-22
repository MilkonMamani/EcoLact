<?php

namespace App\Http\Controllers\Web\PersonalVentas;

use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Ventas\Contracts\VentasListadoQuery;
use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\ObtenerProductosActivos;
use App\Application\Ventas\ObtenerStockProducto;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Application\Ventas\VentasFiltro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del VENTAS: "Ventas" (Historial + Registrar venta). Reutiliza los
 * MISMOS Use Cases que admin.ventas.* (Web\Ventas\VentaProductoController,
 * ningún Use Case ni Repository se duplica); solo cambia la vista servida.
 */
final class VentaController extends Controller
{
    public function __construct(
        private readonly RegistrarVentaProducto $registrarVentaProducto,
        private readonly VentasListadoQuery $ventasListadoQuery,
        private readonly ObtenerProductosActivos $obtenerProductosActivos,
        private readonly ObtenerCompradores $obtenerCompradores,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerStockProducto $obtenerStockProducto,
    ) {}

    public function index(Request $request): View
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

        return view('ventas-panel.ventas.index', [
            'datos' => $this->ventasListadoQuery->obtener($filtro),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', VentaProductoModel::class);

        $productos = $this->obtenerProductosActivos->execute();
        $stockPorProducto = collect($productos)->mapWithKeys(
            fn ($p) => [$p->id() => $this->obtenerStockProducto->execute($p->id())?->cantidadActual() ?? 0.0]
        );

        return view('ventas-panel.ventas.create', [
            'productos' => $productos,
            'stockPorProducto' => $stockPorProducto,
            'compradores' => $this->obtenerCompradores->execute(),
            'proveedores' => $this->obtenerProveedores->execute(),
        ]);
    }

    public function show(VentaProductoModel $venta): View
    {
        Gate::authorize('view', $venta);

        $venta->load(['producto', 'proveedor', 'comprador']);

        return view('ventas-panel.ventas.show', ['venta' => $venta]);
    }

    public function store(StoreVentaProductoRequest $request): RedirectResponse
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

        return redirect()->route('ventas.ventas.show', $venta->id())->with('success', 'Venta registrada correctamente.');
    }
}
