<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Dashboard\DashboardAdministradorData;
use App\Application\Dashboard\ObtenerDashboardAdministrador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardPeriodoRequest;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardAdministrador $obtenerDashboard,
    ) {}

    public function __invoke(DashboardPeriodoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $dashboard = $this->obtenerDashboard->execute(
            periodo: $datos['periodo'] ?? 'hoy',
            desde: $datos['desde'] ?? null,
            hasta: $datos['hasta'] ?? null,
        );

        return response()->json(['data' => $this->serializar($dashboard)]);
    }

    /** @return array<string, mixed> */
    private function serializar(DashboardAdministradorData $dashboard): array
    {
        return [
            'periodo' => [
                'nombre' => $dashboard->periodo->nombre,
                'desde' => $dashboard->periodo->desde->toIso8601String(),
                'hasta' => $dashboard->periodo->hasta->toIso8601String(),
                'etiqueta' => $dashboard->periodo->etiqueta(),
            ],
            'acopio' => $dashboard->acopio,
            'produccion' => $dashboard->produccion,
            'ventas' => $dashboard->ventas,
            'finanzas' => $dashboard->finanzas->toArray(),
            'acopio_por_intervalo' => $dashboard->acopioPorIntervalo,
            'acopio_por_zona' => $dashboard->acopioPorZona,
            'por_atender' => $dashboard->porAtender,
            'existencias_actuales' => $dashboard->existencias,
            'lotes_recientes' => $dashboard->lotesRecientes,
            'asistencia' => $dashboard->asistencia,
            'comunicados' => $dashboard->comunicados,
        ];
    }
}
