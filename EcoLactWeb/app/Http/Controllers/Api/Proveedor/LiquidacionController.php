<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Liquidacion\CalcularPago;
use App\Application\Liquidacion\LiquidacionDetalleData;
use App\Application\Liquidacion\ObtenerDetalleLiquidacion;
use App\Application\Liquidacion\ObtenerLiquidaciones;
use App\Application\Liquidacion\ObtenerResumenLiquidacionesProveedor;
use App\Domain\Pago\AjusteLiquidacion;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\PagoProveedor;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class LiquidacionController extends Controller
{
    public function __construct(
        private readonly ObtenerLiquidaciones $obtenerLiquidaciones,
        private readonly ObtenerResumenLiquidacionesProveedor $obtenerResumenLiquidacionesProveedor,
        private readonly ObtenerDetalleLiquidacion $obtenerDetalleLiquidacion,
        private readonly CalcularPago $calcularPago,
    ) {}

    public function index(Request $request): JsonResponse
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
            ...$this->serializarLiquidacion($liquidacion),
            'saldo_pendiente' => max(0.0, $this->calcularPago->execute($liquidacion->id())),
        ])->all();

        $resumen = $proveedorId !== null ? $this->obtenerResumenLiquidacionesProveedor->execute($proveedorId) : null;

        return response()->json([
            'data' => [
                'filas' => $filas,
                'resumen' => $resumen === null ? null : [
                    'ultima_liquidacion_id' => $resumen->ultimaLiquidacion?->id(),
                    'por_cobrar' => $resumen->porCobrar,
                    'pagado' => $resumen->pagado,
                    'total_liquidaciones' => $resumen->totalLiquidaciones,
                ],
            ],
        ]);
    }

    public function show(LiquidacionModel $liquidacion): JsonResponse
    {
        Gate::authorize('view', $liquidacion);

        $detalle = $this->obtenerDetalleLiquidacion->execute($liquidacion->id);

        if ($detalle === null) {
            abort(404);
        }

        return response()->json(['data' => $this->serializarDetalle($detalle)]);
    }

    /** @return array<string, mixed> */
    private function serializarDetalle(LiquidacionDetalleData $detalle): array
    {
        return [
            'liquidacion' => $this->serializarLiquidacion($detalle->liquidacion),
            'entregas' => $detalle->entregas,
            'incidencia' => $detalle->incidencia,
            'compras' => array_map($this->serializarCompra(...), $detalle->compras),
            'ajustes' => array_map($this->serializarAjuste(...), $detalle->ajustes),
            'pagos' => array_map($this->serializarPago(...), $detalle->pagos),
            'saldo_pendiente' => $detalle->saldoPendiente,
        ];
    }

    /** @return array<string, mixed> */
    private function serializarLiquidacion(Liquidacion $liquidacion): array
    {
        return [
            'id' => $liquidacion->id(),
            'periodo_id' => $liquidacion->periodoId(),
            'total_litros_entregados' => $liquidacion->totalLitrosEntregados(),
            'total_litros_pagables' => $liquidacion->totalLitrosPagables(),
            'precio_litro_aplicado' => $liquidacion->precioLitroAplicado(),
            'subtotal' => $liquidacion->subtotal(),
            'total_ajustes' => $liquidacion->totalAjustes(),
            'total_pagar' => $liquidacion->totalPagar(),
            'estado' => $liquidacion->estado()->value,
            'fecha_calculo' => $liquidacion->fechaCalculo()->format(DATE_ATOM),
            'tuvo_incidencia' => $liquidacion->tuvoIncidencia(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarCompra(VentaProducto $venta): array
    {
        return [
            'id' => $venta->id(),
            'producto_id' => $venta->productoId(),
            'cantidad' => $venta->cantidad(),
            'importe_total' => $venta->importeTotal(),
            'forma_pago' => $venta->formaPago(),
            'fecha' => $venta->fecha()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarAjuste(AjusteLiquidacion $ajuste): array
    {
        return [
            'id' => $ajuste->id(),
            'tipo' => $ajuste->tipo(),
            'descripcion' => $ajuste->descripcion(),
            'monto' => $ajuste->monto(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarPago(PagoProveedor $pago): array
    {
        return [
            'id' => $pago->id(),
            'fecha_pago' => $pago->fechaPago()->format(DATE_ATOM),
            'monto' => $pago->monto(),
            'estado' => $pago->estado()->value,
            'observacion' => $pago->observacion(),
        ];
    }
}
