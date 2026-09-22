<?php

namespace App\Http\Controllers\Web\Ventas;

use App\Application\Ventas\Contracts\VentasListadoQuery;
use App\Application\Ventas\ObtenerCompradores;
use App\Application\Ventas\ObtenerProductosActivos;
use App\Application\Ventas\ObtenerVentasProveedor;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Application\Ventas\VentasFiltro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class VentaProductoController extends Controller
{
    public function __construct(
        private readonly RegistrarVentaProducto $registrarVentaProducto,
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
        private readonly VentasListadoQuery $ventasListadoQuery,
        private readonly ObtenerProductosActivos $obtenerProductosActivos,
        private readonly ObtenerCompradores $obtenerCompradores,
    ) {}

    /**
     * Listado general de ventas (todos los proveedores/compradores/público),
     * con el mismo formulario de "Nueva venta" desde donde se registra.
     */
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

        return view('ventas.ventas.general', [
            'datos' => $this->ventasListadoQuery->obtener($filtro),
            'productos' => $this->obtenerProductosActivos->execute(),
            'compradores' => $this->obtenerCompradores->execute(),
        ]);
    }

    public function show(VentaProductoModel $venta): View
    {
        Gate::authorize('view', $venta);

        $venta->load(['producto', 'proveedor', 'comprador']);

        return view('ventas.ventas.show', ['venta' => $venta]);
    }

    public function porProveedor(int $proveedorId): View
    {
        Gate::authorize('viewAny', VentaProductoModel::class);

        return view('ventas.ventas.index', [
            'ventas' => $this->obtenerVentasProveedor->execute($proveedorId),
        ]);
    }

    public function store(StoreVentaProductoRequest $request): RedirectResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        $datos = $request->validated();

        $this->registrarVentaProducto->execute(
            productoId: (int) $datos['producto_id'],
            tipoCliente: $datos['tipo_cliente'],
            cantidad: (float) $datos['cantidad'],
            formaPago: $datos['forma_pago'],
            registradoPorUserId: $request->user()->id,
            proveedorId: isset($datos['proveedor_id']) ? (int) $datos['proveedor_id'] : null,
            compradorId: isset($datos['comprador_id']) ? (int) $datos['comprador_id'] : null,
        );

        return redirect()->back()->with('success', 'Venta registrada correctamente.');
    }
}
