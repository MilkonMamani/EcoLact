<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Liquidacion\ObtenerPago;
use App\Application\Liquidacion\ObtenerPagos;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del PROVEEDOR: siempre sus propios pagos, nunca un
 * proveedor_id externo.
 */
final class PagoController extends Controller
{
    public function __construct(
        private readonly ObtenerPagos $obtenerPagos,
        private readonly ObtenerPago $obtenerPago,
    ) {}

    /**
     * Vista futura (Etapa 10.3): proveedor.pagos.index
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PagoProveedorModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $pagos = $proveedorId !== null ? $this->obtenerPagos->execute($proveedorId) : [];

        return view('proveedor.pagos.index', ['pagos' => $pagos]);
    }

    /**
     * Vista futura (Etapa 10.3): proveedor.pagos.show
     */
    public function show(PagoProveedorModel $pago): View
    {
        Gate::authorize('view', $pago);

        return view('proveedor.pagos.show', [
            'pago' => $this->obtenerPago->execute($pago->id),
        ]);
    }
}
