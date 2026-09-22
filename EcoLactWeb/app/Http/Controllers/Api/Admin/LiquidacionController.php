<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Liquidacion\Contracts\LiquidacionesListadoQuery;
use App\Application\Liquidacion\LiquidacionDetalleData;
use App\Application\Liquidacion\LiquidacionesFiltro;
use App\Application\Liquidacion\ObtenerDetalleLiquidacion;
use App\Application\Liquidacion\ObtenerLiquidacion;
use App\Domain\Pago\AjusteLiquidacion;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\PagoProveedor;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class LiquidacionController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly LiquidacionesListadoQuery $liquidacionesListadoQuery,
        private readonly ObtenerLiquidacion $obtenerLiquidacion,
        private readonly ObtenerDetalleLiquidacion $obtenerDetalleLiquidacion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LiquidacionModel::class);

        $filtro = LiquidacionesFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            incidencia: $request->string('incidencia')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            periodoId: $request->filled('periodo_id') ? $request->integer('periodo_id') : null,
        );

        $datos = $this->liquidacionesListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => [
                'kpis' => $datos->kpis,
                'zonas' => $datos->zonas,
                'periodos' => $datos->periodos,
                'filas' => $filas,
            ],
            'meta' => $meta,
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

    public function pdf(LiquidacionModel $liquidacion): Response
    {
        Gate::authorize('view', $liquidacion);

        $liquidacionDominio = $this->obtenerLiquidacion->execute($liquidacion->id);
        $detalle = $this->obtenerDetalleLiquidacion->execute($liquidacion->id);

        if ($liquidacionDominio === null || $detalle === null) {
            abort(404);
        }

        $periodo = PeriodoLiquidacionModel::find($liquidacion->periodo_id);

        return Pdf::loadView('pdf.liquidacion', [
            'liquidacion' => $liquidacionDominio,
            'detalle' => $detalle,
            'periodo' => $periodo,
        ])->stream('liquidacion-'.$liquidacion->id.'.pdf');
    }

    public function pdfPeriodo(Request $request, PeriodoLiquidacionModel $periodo): Response
    {
        Gate::authorize('viewAny', LiquidacionModel::class);

        $hojas = LiquidacionModel::where('periodo_id', $periodo->id)
            ->whereIn('estado', ['PENDIENTE', 'PARCIALMENTE_PAGADA'])
            ->when(
                $request->filled('zona_id'),
                fn ($query) => $query->whereHas('proveedor', fn ($q) => $q->where('zona_id', $request->integer('zona_id'))),
            )
            ->get()
            ->map(fn (LiquidacionModel $modelo): array => [
                'liquidacion' => $this->obtenerLiquidacion->execute($modelo->id),
                'detalle' => $this->obtenerDetalleLiquidacion->execute($modelo->id),
            ])
            ->sortBy(fn (array $hoja) => $hoja['detalle']->proveedorNombre)
            ->values();

        $zona = $request->filled('zona_id') ? ZonaModel::find($request->integer('zona_id')) : null;

        return Pdf::loadView('pdf.liquidacion-periodo', [
            'periodo' => $periodo,
            'zona' => $zona,
            'hojas' => $hojas,
        ])->stream('liquidaciones-periodo-'.$periodo->id.'.pdf');
    }

    /** @return array<string, mixed> */
    private function serializarDetalle(LiquidacionDetalleData $detalle): array
    {
        return [
            'liquidacion' => $this->serializarLiquidacion($detalle->liquidacion),
            'proveedor_nombre' => $detalle->proveedorNombre,
            'proveedor_dni' => $detalle->proveedorDni,
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
            'proveedor_id' => $liquidacion->proveedorId(),
            'total_litros_entregados' => $liquidacion->totalLitrosEntregados(),
            'total_litros_pagables' => $liquidacion->totalLitrosPagables(),
            'subtotal' => $liquidacion->subtotal(),
            'total_ajustes' => $liquidacion->totalAjustes(),
            'total_pagar' => $liquidacion->totalPagar(),
            'estado' => $liquidacion->estado()->value,
            'fecha_calculo' => $liquidacion->fechaCalculo()->format(DATE_ATOM),
            'generado_por_user_id' => $liquidacion->generadoPorUserId(),
            'precio_litro_aplicado' => $liquidacion->precioLitroAplicado(),
            'tuvo_incidencia' => $liquidacion->tuvoIncidencia(),
            'sancion_id' => $liquidacion->sancionId(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarCompra(VentaProducto $venta): array
    {
        return [
            'id' => $venta->id(),
            'producto_id' => $venta->productoId(),
            'proveedor_id' => $venta->proveedorId(),
            'comprador_id' => $venta->compradorId(),
            'tipo_cliente' => $venta->tipoCliente(),
            'cantidad' => $venta->cantidad(),
            'precio_unitario' => $venta->precioUnitario(),
            'importe_total' => $venta->importeTotal(),
            'forma_pago' => $venta->formaPago(),
            'periodo_id' => $venta->periodoId(),
            'estado' => $venta->estado(),
            'fecha' => $venta->fecha()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarAjuste(AjusteLiquidacion $ajuste): array
    {
        return [
            'id' => $ajuste->id(),
            'liquidacion_id' => $ajuste->liquidacionId(),
            'sancion_id' => $ajuste->sancionId(),
            'tipo' => $ajuste->tipo(),
            'descripcion' => $ajuste->descripcion(),
            'monto' => $ajuste->monto(),
            'creado_por_user_id' => $ajuste->creadoPorUserId(),
            'venta_producto_id' => $ajuste->ventaProductoId(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarPago(PagoProveedor $pago): array
    {
        return [
            'id' => $pago->id(),
            'liquidacion_id' => $pago->liquidacionId(),
            'fecha_pago' => $pago->fechaPago()->format(DATE_ATOM),
            'monto' => $pago->monto(),
            'estado' => $pago->estado()->value,
            'registrado_por_user_id' => $pago->registradoPorUserId(),
            'observacion' => $pago->observacion(),
        ];
    }
}
