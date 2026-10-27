<?php

namespace App\Application\Calidad;

use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Application\Comunicacion\ObtenerComunicados;
use DateTimeImmutable;

/**
 * Compone el Dashboard de CALIDAD. Calidad trabaja por MUESTREO: lo
 * pendiente son las muestras seleccionadas (MuestreosCalidadQuery), nunca
 * todas las entregas sin análisis. Los KPIs del periodo salen de la misma
 * Query de análisis que el listado admin.
 */
final class ObtenerDashboardCalidad
{
    private const MAX_FILAS_PREVIA = 8;

    private const MAX_COMUNICADOS_PREVIA = 3;

    public function __construct(
        private readonly MuestreosCalidadQuery $muestreos,
        private readonly AnalisisCalidadListadoQuery $analisisCalidadListadoQuery,
        private readonly ObtenerComunicados $obtenerComunicados,
    ) {}

    public function execute(int $userId, ?string $periodo, ?string $desde, ?string $hasta): DashboardCalidadData
    {
        $muestras = $this->muestreos->pendientes();
        $kpisMuestreo = $this->muestreos->kpis(new DateTimeImmutable);

        $analisis = $this->analisisCalidadListadoQuery->obtener(
            AnalisisCalidadFiltro::resolver(busqueda: null, zonaId: null, resultado: null, estado: null, fecha: $periodo, desde: $desde, hasta: $hasta)
        );

        $comunicados = $this->obtenerComunicados->execute($userId);
        usort($comunicados, static fn ($a, $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        return new DashboardCalidadData(
            muestrasPendientes: $kpisMuestreo['pendientes'],
            analizadasHoy: $kpisMuestreo['analizadas_hoy'],
            kpisPeriodo: [
                'analizados' => $analisis->kpis['total'],
                'aceptados' => $analisis->kpis['aceptadas'],
                'observados' => $analisis->kpis['observadas'],
                'rechazados' => $analisis->kpis['rechazadas'],
            ],
            muestrasFilas: array_slice($muestras, 0, self::MAX_FILAS_PREVIA),
            ultimosAnalisisFilas: array_slice($analisis->filas, 0, self::MAX_FILAS_PREVIA),
            comunicadosRecientes: array_slice($comunicados, 0, self::MAX_COMUNICADOS_PREVIA),
        );
    }
}
