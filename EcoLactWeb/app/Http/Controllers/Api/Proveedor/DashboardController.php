<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Proveedor\DashboardProveedorData;
use App\Application\Proveedor\ObtenerDashboardProveedor;
use App\Domain\Comunicacion\Comunicado;
use App\Domain\Entrega\Entrega;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardProveedor $obtenerDashboardProveedor,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $proveedorId = $request->user()->proveedor?->id
            ?? throw new DomainException('No existe un proveedor asociado al usuario autenticado.');

        $dashboard = $this->obtenerDashboardProveedor->execute(
            userId: $request->user()->id,
            proveedorId: $proveedorId,
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return response()->json(['data' => $this->serializar($dashboard)]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardProveedorData $dashboard): array
    {
        return [
            'kpis' => $dashboard->kpis,
            'ultimo_analisis_resultado' => $dashboard->ultimoAnalisis?->resultado()?->value,
            'ultima_liquidacion' => $dashboard->ultimaLiquidacion === null ? null : [
                'id' => $dashboard->ultimaLiquidacion->id(),
                'total_pagar' => $dashboard->ultimaLiquidacion->totalPagar(),
                'estado' => $dashboard->ultimaLiquidacion->estado()->value,
            ],
            'ultima_liquidacion_periodo' => $dashboard->ultimaLiquidacionPeriodo === null ? null : [
                'fecha_inicio' => $dashboard->ultimaLiquidacionPeriodo->fechaInicio()->format('Y-m-d'),
                'fecha_fin' => $dashboard->ultimaLiquidacionPeriodo->fechaFin()->format('Y-m-d'),
            ],
            'por_cobrar' => $dashboard->porCobrar,
            'ultimas_entregas' => array_map($this->serializarEntrega(...), $dashboard->ultimasEntregas),
            'compras_recientes' => array_map($this->serializarCompra(...), $dashboard->comprasRecientes),
            'comunicados_recientes' => array_map($this->serializarComunicado(...), $dashboard->comunicadosRecientes),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarEntrega(Entrega $entrega): array
    {
        return [
            'id' => $entrega->id(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'estado' => $entrega->estado()->value,
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
    private function serializarComunicado(Comunicado $comunicado): array
    {
        return [
            'id' => $comunicado->id(),
            'titulo' => $comunicado->titulo(),
            'tipo' => $comunicado->tipo(),
            'fecha_publicacion' => $comunicado->fechaPublicacion()->format(DATE_ATOM),
        ];
    }
}
