<?php

namespace App\Http\Controllers\Web\Produccion;

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\ObtenerStockGeneral;
use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Application\Produccion\RegistrarAjusteProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\StoreAjusteStockRequest;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Punto de consulta unificado de inventario (productos terminados +
 * insumos), exclusivo de ADMINISTRADOR (Gate::before). Reutiliza
 * ProductoStockPolicy: es la misma habilidad que ya gobierna el inventario
 * de productos terminados.
 */
final class StockController extends Controller
{
    public function __construct(
        private readonly ObtenerStockGeneral $obtenerStockGeneral,
        private readonly ObtenerDetalleStock $obtenerDetalleStock,
        private readonly RegistrarAjusteProducto $registrarAjusteProducto,
        private readonly RegistrarAjusteInsumo $registrarAjusteInsumo,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        return view('admin.stock.index', [
            'items' => $this->obtenerStockGeneral->execute(),
        ]);
    }

    public function show(string $tipo, int $id): View
    {
        Gate::authorize('viewAny', ProductoMovimientoModel::class);

        $detalle = $this->obtenerDetalleStock->execute(strtoupper($tipo), $id);

        abort_if($detalle === null, 404);

        return view('admin.stock.show', ['detalle' => $detalle]);
    }

    public function ajustar(StoreAjusteStockRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProductoMovimientoModel::class);

        $datos = $request->validated();

        if ($datos['tipo'] === 'PRODUCTO') {
            $this->registrarAjusteProducto->execute(
                productoId: (int) $datos['elemento_id'],
                nuevoStock: (float) $datos['nuevo_stock'],
                motivo: $datos['motivo'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
            );
        } else {
            $this->registrarAjusteInsumo->execute(
                insumoId: (int) $datos['elemento_id'],
                nuevoStock: (float) $datos['nuevo_stock'],
                motivo: $datos['motivo'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
            );
        }

        return redirect()->route('admin.stock.index')->with('success', 'Ajuste de inventario registrado correctamente.');
    }
}
