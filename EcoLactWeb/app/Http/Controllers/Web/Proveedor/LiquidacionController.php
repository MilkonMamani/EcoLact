<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Liquidacion\CalcularPago;
use App\Application\Liquidacion\ObtenerDetalleLiquidacion;
use App\Application\Liquidacion\ObtenerLiquidaciones;
use App\Application\Liquidacion\ObtenerResumenLiquidacionesProveedor;
use App\Domain\Pago\Liquidacion;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del PROVEEDOR: siempre sus propias liquidaciones, nunca un
 * proveedor_id externo. El detalle reutiliza ObtenerDetalleLiquidacion
 * (misma agregación que ya usa ADMINISTRADOR: entregas del periodo,
 * incidencia, compras, ajustes e historial de pagos), incluyendo el pago
 * dentro de la propia liquidación en vez de un módulo "Mis pagos" separado,
 * tal como lo especifica el sidebar objetivo del proveedor.
 */
final class LiquidacionController extends Controller
{
    public function __construct(
        private readonly ObtenerLiquidaciones $obtenerLiquidaciones,
        private readonly ObtenerResumenLiquidacionesProveedor $obtenerResumenLiquidacionesProveedor,
        private readonly ObtenerDetalleLiquidacion $obtenerDetalleLiquidacion,
        private readonly CalcularPago $calcularPago,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LiquidacionModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $liquidaciones = $proveedorId !== null ? $this->obtenerLiquidaciones->execute($proveedorId) : [];

        $filtradas = collect($liquidaciones)->filter(function (Liquidacion $liquidacion) use ($request): bool {
            if ($request->filled('estado') && $liquidacion->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            return true;
        })->sortByDesc(fn (Liquidacion $l) => $l->fechaCalculo())->values();

        $filas = $filtradas->map(fn (Liquidacion $liquidacion): array => [
            'liquidacion' => $liquidacion,
            'saldoPendiente' => max(0.0, $this->calcularPago->execute($liquidacion->id())),
        ])->all();

        $resumen = $proveedorId !== null
            ? $this->obtenerResumenLiquidacionesProveedor->execute($proveedorId)
            : null;

        return view('proveedor.liquidaciones.index', [
            'filas' => $filas,
            'resumen' => $resumen,
        ]);
    }

    public function show(LiquidacionModel $liquidacion): View
    {
        Gate::authorize('view', $liquidacion);

        return view('proveedor.liquidaciones.show', [
            'detalle' => $this->obtenerDetalleLiquidacion->execute($liquidacion->id),
        ]);
    }
}
