<?php

namespace App\Http\Controllers\Web\PersonalVentas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreVentaProductoRequest;
use App\Http\Support\BoletaVenta;
use App\Http\Support\PantallaVentas;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del VENTAS: "Ventas / POS" (Nueva venta + Historial). Reutiliza la
 * MISMA pantalla y los MISMOS Use Cases que admin.ventas.* (PantallaVentas);
 * solo cambian el layout y el prefijo de rutas.
 */
final class VentaController extends Controller
{
    public function __construct(
        private readonly PantallaVentas $pantalla,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewGeneral', VentaProductoModel::class);

        return $this->pantalla->index('ventas', $request);
    }

    /**
     * Ruta antigua de "Registrar venta": ahora es la pestaña Nueva venta.
     */
    public function create(): RedirectResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        return redirect()->route('ventas.ventas.index');
    }

    public function show(Request $request, VentaProductoModel $venta): View
    {
        Gate::authorize('view', $venta);

        return $this->pantalla->show('ventas', $request, $venta);
    }

    /** PDF de la boleta (EcoLact_Venta_<codigo>.pdf), derivado de la venta registrada. */
    public function boleta(VentaProductoModel $venta): Response
    {
        Gate::authorize('view', $venta);

        return BoletaVenta::pdf($venta);
    }

    public function saldoProveedor(int $proveedor): JsonResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        return $this->pantalla->saldoProveedor($proveedor);
    }

    public function store(StoreVentaProductoRequest $request): RedirectResponse
    {
        Gate::authorize('create', VentaProductoModel::class);

        return $this->pantalla->store('ventas', $request);
    }
}
