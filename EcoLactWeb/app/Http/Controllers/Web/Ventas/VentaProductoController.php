<?php

namespace App\Http\Controllers\Web\Ventas;

use App\Application\Ventas\ObtenerVentasProveedor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Http\Support\PantallaVentas;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ventas / POS del ADMINISTRADOR. La pantalla, el registro y el detalle son
 * los mismos que los del PERSONAL DE VENTAS (PantallaVentas).
 */
final class VentaProductoController extends Controller
{
    public function __construct(
        private readonly PantallaVentas $pantalla,
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
    ) {}

    /**
     * Pestañas "Nueva venta" e "Historial" (?tab=historial).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewGeneral', VentaProductoModel::class);

        return $this->pantalla->index('admin', $request);
    }

    public function show(Request $request, VentaProductoModel $venta): View
    {
        Gate::authorize('view', $venta);

        return $this->pantalla->show('admin', $request, $venta);
    }

    public function porProveedor(int $proveedorId): View
    {
        Gate::authorize('viewAny', VentaProductoModel::class);

        return view('ventas.ventas.index', [
            'ventas' => $this->obtenerVentasProveedor->execute($proveedorId),
        ]);
    }

    public function saldoProveedor(int $proveedor): JsonResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        return $this->pantalla->saldoProveedor($proveedor);
    }

    public function store(StoreVentaProductoRequest $request): RedirectResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        return $this->pantalla->store('admin', $request);
    }
}
