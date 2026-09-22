<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Application\PersonalVentas\DashboardVentasData;
use App\Application\PersonalVentas\ObtenerDashboardVentas;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardVentas $obtenerDashboardVentas,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dashboard = $this->obtenerDashboardVentas->execute(
            userId: $request->user()->id,
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return response()->json(['data' => $this->serializar($dashboard)]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardVentasData $dashboard): array
    {
        return [
            'kpis' => $dashboard->kpis,
            'ventas_recientes' => array_map($this->serializarVenta(...), $dashboard->ventasRecientes),
            'stock_critico' => $dashboard->stockCritico,
            'mas_vendidos' => $dashboard->masVendidos,
            'notificaciones_recientes' => array_map($this->serializarNotificacion(...), $dashboard->notificacionesRecientes),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarVenta(VentaProducto $venta): array
    {
        return [
            'id' => $venta->id(),
            'producto_id' => $venta->productoId(),
            'proveedor_id' => $venta->proveedorId(),
            'comprador_id' => $venta->compradorId(),
            'tipo_cliente' => $venta->tipoCliente(),
            'cantidad' => $venta->cantidad(),
            'importe_total' => $venta->importeTotal(),
            'forma_pago' => $venta->formaPago(),
            'estado' => $venta->estado(),
            'fecha' => $venta->fecha()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarNotificacion(Notificacion $notificacion): array
    {
        return [
            'id' => $notificacion->id(),
            'tipo' => $notificacion->tipo(),
            'titulo' => $notificacion->titulo(),
            'mensaje' => $notificacion->mensaje(),
            'leido' => $notificacion->leido(),
        ];
    }
}
