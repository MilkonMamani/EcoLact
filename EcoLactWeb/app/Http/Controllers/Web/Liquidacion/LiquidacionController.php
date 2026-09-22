<?php

namespace App\Http\Controllers\Web\Liquidacion;

use App\Application\Liquidacion\ObtenerDetalleLiquidacion;
use App\Application\Liquidacion\ObtenerLiquidacion;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ya no existe una pantalla de Liquidaciones: el cálculo nace desde
 * Configuración de pagos y el administrador solo ve Pagos. Este Controller
 * conserva únicamente la hoja imprimible (sobre de pago) de la liquidación
 * de un proveedor, que se abre desde el detalle de su pago.
 */
final class LiquidacionController extends Controller
{
    public function __construct(
        private readonly ObtenerLiquidacion $obtenerLiquidacion,
        private readonly ObtenerDetalleLiquidacion $obtenerDetalleLiquidacion,
    ) {}

    /**
     * Reutiliza los MISMOS datos del cálculo (ObtenerLiquidacion +
     * ObtenerDetalleLiquidacion): es solo una presentación pensada para
     * papel, no un cálculo nuevo.
     */
    public function imprimir(LiquidacionModel $liquidacion): View
    {
        Gate::authorize('view', $liquidacion);

        return view('admin.liquidaciones.imprimir', [
            'liquidacion' => $this->obtenerLiquidacion->execute($liquidacion->id),
            'detalle' => $this->obtenerDetalleLiquidacion->execute($liquidacion->id),
            'periodo' => PeriodoLiquidacionModel::find($liquidacion->periodo_id),
        ]);
    }
}
