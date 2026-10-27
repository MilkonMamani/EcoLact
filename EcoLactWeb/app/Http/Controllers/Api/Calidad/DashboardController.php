<?php

namespace App\Http\Controllers\Api\Calidad;

use App\Application\Calidad\DashboardCalidadData;
use App\Application\Calidad\ObtenerDashboardCalidad;
use App\Domain\Comunicacion\Comunicado;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardCalidad $obtenerDashboardCalidad,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dashboard = $this->obtenerDashboardCalidad->execute(
            userId: $request->user()->id,
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return response()->json(['data' => $this->serializar($dashboard)]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardCalidadData $dashboard): array
    {
        return [
            // Muestras seleccionadas sin analizar (no todas las entregas).
            'muestras_pendientes' => $dashboard->muestrasPendientes,
            'analizadas_hoy' => $dashboard->analizadasHoy,
            'kpis_periodo' => $dashboard->kpisPeriodo,
            'muestras_filas' => $dashboard->muestrasFilas,
            'ultimos_analisis_filas' => $dashboard->ultimosAnalisisFilas,
            'comunicados_recientes' => array_map($this->serializarComunicado(...), $dashboard->comunicadosRecientes),
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
