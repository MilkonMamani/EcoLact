<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Planta\DashboardPlantaData;
use App\Application\Planta\ObtenerDashboardPlanta;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\LoteProduccion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardPlanta $obtenerDashboardPlanta,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dashboard = $this->obtenerDashboardPlanta->execute(
            userId: $request->user()->id,
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return response()->json(['data' => $this->serializar($dashboard)]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardPlantaData $dashboard): array
    {
        return [
            'kpis' => $dashboard->kpis,
            'produccion_actual' => array_map(fn (LoteProduccion $lote): array => $this->serializarLote($lote, $dashboard->litrosPorLote), $dashboard->produccionActual),
            'produccion_reciente' => array_map(fn (LoteProduccion $lote): array => $this->serializarLote($lote, $dashboard->litrosPorLote), $dashboard->produccionReciente),
            'insumos_bajo_minimo' => array_map($this->serializarInsumo(...), $dashboard->insumosBajoMinimo),
            'inventario' => $dashboard->inventario,
            'productos_disponibles' => $dashboard->productosDisponibles,
            'notificaciones_recientes' => array_map($this->serializarNotificacion(...), $dashboard->notificacionesRecientes),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarLote(LoteProduccion $lote, array $litrosPorLote): array
    {
        return [
            'id' => $lote->id(),
            'codigo' => $lote->codigo(),
            'fecha_hora_inicio' => $lote->fechaHoraInicio()->format(DATE_ATOM),
            'fecha_hora_fin' => $lote->fechaHoraFin()?->format(DATE_ATOM),
            'litros_leche_utilizados' => $litrosPorLote[$lote->id()] ?? 0.0,
            'estado' => $lote->estado(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarInsumo(Insumo $insumo): array
    {
        return [
            'id' => $insumo->id(),
            'nombre' => $insumo->nombre(),
            'unidad_medida' => $insumo->unidadBase(),
            'unidad_base' => $insumo->unidadBase(),
            'stock_actual' => $insumo->stockActual(),
            'stock_minimo' => $insumo->stockMinimo(),
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
