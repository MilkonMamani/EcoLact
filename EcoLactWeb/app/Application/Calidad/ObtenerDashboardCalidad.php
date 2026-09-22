<?php

namespace App\Application\Calidad;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Application\Comunicacion\ObtenerComunicados;

/**
 * Compone el Dashboard de CALIDAD a partir de las Query ya existentes para
 * Entregas y Análisis de calidad (las mismas que usa el listado admin), sin
 * duplicar su lógica de agregación.
 */
final class ObtenerDashboardCalidad
{
    private const MAX_FILAS_PREVIA = 8;

    private const MAX_COMUNICADOS_PREVIA = 3;

    public function __construct(
        private readonly EntregasListadoQuery $entregasListadoQuery,
        private readonly AnalisisCalidadListadoQuery $analisisCalidadListadoQuery,
        private readonly ObtenerComunicados $obtenerComunicados,
    ) {}

    public function execute(int $userId, ?string $periodo, ?string $desde, ?string $hasta): DashboardCalidadData
    {
        $pendientes = $this->entregasListadoQuery->obtener(
            EntregasFiltro::resolver(busqueda: null, zonaId: null, acopiadorId: null, estado: 'PENDIENTE_ANALISIS', fecha: null, desde: null, hasta: null)
        );

        $analisis = $this->analisisCalidadListadoQuery->obtener(
            AnalisisCalidadFiltro::resolver(busqueda: null, zonaId: null, resultado: null, estado: null, fecha: $periodo, desde: $desde, hasta: $hasta)
        );

        $comunicados = $this->obtenerComunicados->execute($userId);
        usort($comunicados, static fn ($a, $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        return new DashboardCalidadData(
            pendientesAnalisis: $pendientes->kpis['pendientesAnalisis'],
            kpisPeriodo: [
                'analizados' => $analisis->kpis['total'],
                'aceptados' => $analisis->kpis['aceptadas'],
                'observados' => $analisis->kpis['observadas'],
                'rechazados' => $analisis->kpis['rechazadas'],
            ],
            pendientesFilas: array_slice($pendientes->filas, 0, self::MAX_FILAS_PREVIA),
            ultimosAnalisisFilas: array_slice($analisis->filas, 0, self::MAX_FILAS_PREVIA),
            comunicadosRecientes: array_slice($comunicados, 0, self::MAX_COMUNICADOS_PREVIA),
        );
    }
}
